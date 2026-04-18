<?php

namespace Sidalex\SwooleApp;

use Sidalex\SwooleApp\Classes\Builder\ConfigBuilder;
use Sidalex\SwooleApp\Classes\Constants\ApplicationConstants;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobRunner;
use Sidalex\SwooleApp\Classes\Tasks\Executors\TaskExecutorInterface;
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;
use Sidalex\SwooleApp\Classes\Builder\NotFoundControllerBuilder;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobOrchestrator;
use Sidalex\SwooleApp\Classes\Dispatcher\DispatcherInterface;
use Sidalex\SwooleApp\Classes\Initiation\StateContainerInitiationInterface;
use Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Sidalex\SwooleApp\Classes\Wrapper\StateContainerWrapper;
use Swoole\Coroutine;
use Swoole\Http\Server;

class Application {
    protected ConfigWrapper $config;
    protected ConfigBuilder $configBuilder;
    /**
     * @var array<mixed>
     */
    protected array $routesCollection;
    protected StateContainerWrapper $stateContainer;

    /**
     * @var array<array{class: string, options: array<mixed>}>
     */
    protected array $globalMiddlewares = [];
    protected ?CyclicJobOrchestrator $cyclicJobOrchestrator = null;
    protected ?Server $server = null;
    protected ?DispatcherInterface $dispatcher = null;

    /**
     * @param \stdClass|null $baseConfig
     * @param string[] $configValidators
     * @param ConfigBuilder|null $configBuilder
     * @param RoutesCollectionBuilder|null $routesCollectionBuilder
     * @throws \ReflectionException
     */
    public function __construct(
        ?\stdClass $baseConfig = null,
        array $configValidators = [],
        ?ConfigBuilder $configBuilder = null,
        ?RoutesCollectionBuilder $routesCollectionBuilder = null,
    ) {
        try {
            $this->configBuilder = $configBuilder ?? new ConfigBuilder($baseConfig);
            if (!empty($configValidators) && !$this->configBuilder->validate($configValidators)) {
                throw new \RuntimeException(
                    "Configuration validation failed:\n" .
                    implode("\n", $this->configBuilder->getErrors())
                );
            }

            $this->config = new ConfigWrapper($this->configBuilder->getConfig());
            $this->initGlobalMiddlewares();
            $this->initializeRoutes($routesCollectionBuilder);
            $this->initializeStateContainer();
            $this->initializeWorkerDispatcher();
        } catch (\Exception $e) {
            error_log('Application initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function initializeWorkerDispatcher(): void {
        $dispatcherClass = $this->config->getConfigFromKey('DISPATCHER');

        if (empty($dispatcherClass)) {
            return;
        }

        if (!class_exists($dispatcherClass)) {
            error_log("[SwooleApp] Класс диспатчера {$dispatcherClass} не найден");
            return;
        }

        $dispatcherInstance = new $dispatcherClass();

        if (!$dispatcherInstance instanceof DispatcherInterface) {
            error_log("[SwooleApp] Класс {$dispatcherClass} должен реализовать DispatcherInterface");
            return;
        }

        $this->dispatcher = $dispatcherInstance;
        error_log("[SwooleApp] Загружен пользовательский диспатчер: {$dispatcherClass}");
    }

    /**
     * Создание и настройка Swoole сервера
     */
    public function createServer(string $host = "0.0.0.0", int $port = 9501): Server {
        $this->server = new Server($host, $port, SWOOLE_PROCESS);

        $this->server->set($this->configBuilder->buildServerConfig($this->dispatcher));

        $this->registerServerHandlers();

        return $this->server;
    }

    /**
     * Регистрация всех обработчиков сервера
     */
    protected function registerServerHandlers(): void {
        if (!$this->server) {
            throw new \RuntimeException("Server not created. Call createServer() first.");
        }

        // Обработчик старта воркера с детализацией типа
        $this->server->on("workerStart", function (Server $server, int $workerId) {
            // Определяем тип воркера через свойства сервера
            $isTaskWorker = $server->taskworker;
            $processType = $isTaskWorker ? 'task_worker' : 'worker';

            echo "Process started: type={$processType}, workerId={$workerId}\n";

            // Запускаем циклические задачи только для HTTP воркеров (не task)
            if (!$isTaskWorker) {
                $this->initCyclicJobsWithWorker($server, $workerId);
            } else {
                echo "Process type {$processType} - skipping cyclic jobs.\n";
            }
        });

        // Обработчик остановки воркера
        $this->server->on("workerStop", function (Server $server, int $workerId) {
            $isTaskWorker = $server->taskworker;
            $processType = $isTaskWorker ? 'task_worker' : 'worker';

            echo "Process stopping: type={$processType}, workerId={$workerId}\n";
            $this->shutdownCyclicJobs();
        });

        // Обработчик HTTP запросов
        $this->server->on("request", function ($request, $response) {
            $this->execute($request, $response, $this->server);
        });

        // Обработчик задач
        $this->server->on('task', function (Server $server, $taskId, $reactorId, $data) {
            return $this->taskExecute($server, $taskId, $reactorId, $data);
        });
    }



    /**
     * @deprecated 0.3.0 Будет удален в версии 0.4.0
     */
    public function initCyclicJobs(Server $server): void {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? null;

        $errorMsg = sprintf(
            "\n========== ОШИБКА МИГРАЦИИ SwooleApp ==========\n" .
            "В версии 0.3.0 изменен API циклических задач.\n\n" .
            "Было (устарело):\n" .
            "  \$http->on(\"start\", function(\$http) use (\$app) {\n" .
            "      \$app->initCyclicJobs(\$http);\n" .
            "  });\n\n" .
            "Стало (новый API):\n" .
            "  // Создаем сервер через Application\n" .
            "  \$http = \$app->createServer('0.0.0.0', 9501);\n" .
            "  \$http->start();\n\n" .
            "Все обработчики регистрируются автоматически!\n" .
            "Место вызова: %s:%d\n" .
            "============================================\n",
            $caller['file'] ?? 'unknown',
            $caller['line'] ?? 0
        );

        error_log($errorMsg);
        throw new \RuntimeException($errorMsg);
    }

    /**
     * Инициализация циклических задач в воркере
     */
    public function initCyclicJobsWithWorker(Server $server, int $workerId): void {
        // Проверяем, что это HTTP воркер
        if ($server->taskworker) {
            error_log(sprintf(
                "[Worker %d] Попытка запустить циклические задачи в task-воркере - игнорируется",
                $workerId
            ));
            return;
        }

        $builder = new CyclicJobsBuilder($this->config);
        $listCyclicJobs = $builder->buildCyclicJobs($this, $server);

        if (empty($listCyclicJobs)) {
            return;
        }

        $this->cyclicJobOrchestrator = new CyclicJobOrchestrator(
            $listCyclicJobs,
            $this->config,
            $workerId,
            $server
        );

        $this->cyclicJobOrchestrator->start();
        unset($builder, $listCyclicJobs);
    }

    /**
     * Остановка циклических задач
     */
    public function shutdownCyclicJobs(): void {
        if ($this->cyclicJobOrchestrator) {
            $this->cyclicJobOrchestrator->shutdown();
        }
    }

    /**
     * Инициализация маршрутов
     */
    private function initializeRoutes(?RoutesCollectionBuilder $routesCollectionBuilder): void {
        $routeBuilder = $routesCollectionBuilder ?? new RoutesCollectionBuilder($this->config);
        $this->routesCollection = $routeBuilder->buildRoutesCollection();
    }

    /**
     * Инициализация контейнера состояния
     */
    private function initializeStateContainer(): void {
        $stateContainerInit = $this->config->getConfigFromKey(ApplicationConstants::APP_STATE_CONTAINER_INITIATION_CONFIG_NAME) ?? [];
        if (empty($stateContainerInit)) {
            return;
        }

        $stateContainer = new \stdClass();
        foreach ($stateContainerInit as $initiatorClass) {
            if (!Utilities::classImplementInterface($initiatorClass, StateContainerInitiationInterface::class)) {
                error_log("Skipping invalid state initiator: {$initiatorClass}");
                continue;
            }

            $initiator = new $initiatorClass();
            if ($initiator instanceof StateContainerInitiationInterface) {
                $initiator->init($this);
                $stateContainer->{$initiator->getKey()} = $initiator->getResultInitiation();
            }
        }

        $this->stateContainer = new StateContainerWrapper($stateContainer);
    }

    /**
     * Инициализация глобальных middleware
     */
    protected function initGlobalMiddlewares(): void {
        $globalMiddlewaresConfig = $this->config->getConfigFromKey('globalMiddlewares');
        if (is_array($globalMiddlewaresConfig)) {
            foreach ($globalMiddlewaresConfig as $middlewareConfig) {
                if (is_string($middlewareConfig)) {
                    $this->globalMiddlewares[] = [
                        'class' => $middlewareConfig,
                        'options' => []
                    ];
                } elseif (is_array($middlewareConfig) && isset($middlewareConfig['class'])) {
                    $this->globalMiddlewares[] = [
                        'class' => $middlewareConfig['class'],
                        'options' => $middlewareConfig['options'] ?? []
                    ];
                }
            }
        }
    }

    /**
     * @return array<array{class: string, options: array<mixed>}>
     */
    public function getGlobalMiddlewares(): array {
        return $this->globalMiddlewares;
    }

    /**
     * @return array<mixed>
     */
    public function getRoutesCollection(): array {
        return $this->routesCollection;
    }

    /**
     * Выполнение HTTP запроса
     */
    public function execute(\Swoole\Http\Request $request, \Swoole\Http\Response $response, Server $server): void {
        $Route_builder = new RoutesCollectionBuilder($this->config);
        $itemRouteCollection = $Route_builder->searchInRoute($request, $this->routesCollection);

        if (empty($itemRouteCollection)) {
            $controller = (new NotFoundControllerBuilder($request, $response, $this->config))->build();
        } else {
            $controller = $Route_builder->getController($itemRouteCollection, $request, $response);
        }

        $controller->setApplication($this, $server);
        $response = $controller->executeWithMiddlewares();
        unset($controller);
    }

    /**
     * Получение конфигурации
     */
    public function getConfig(): ConfigWrapper {
        return $this->config;
    }

    /**
     * Выполнение задачи
     */
    public function taskExecute(\Swoole\Http\Server $server, int $taskId, int $reactorId, TaskDataInterface $data): TaskResulted {
        try {
            if (empty($data->getTaskClassName())) {
                throw new \InvalidArgumentException('Task class name is empty');
            }

            $TaskExecutorClassName = $data->getTaskClassName();
            if (!class_exists($TaskExecutorClassName)) {
                throw new \RuntimeException("Task executor class {$TaskExecutorClassName} not found");
            }

            if (!Utilities::classImplementInterface($TaskExecutorClassName, TaskExecutorInterface::class)) {
                throw new \RuntimeException("Class {$TaskExecutorClassName} must implement TaskExecutorInterface");
            }

            $taskExecutor = new $TaskExecutorClassName($server, $taskId, $reactorId, $data, $this);
            if (!$taskExecutor instanceof TaskExecutorInterface) {
                throw new \RuntimeException("Invalid task executor instance");
            }

            return $taskExecutor->execute();
        } catch (\Throwable $e) {
            if ($this->config->getConfigFromKey('APP_DEBUG')) {
                error_log("Task execution failed: " . $e->getMessage());
            }

            $errorDetails = $this->config->getConfigFromKey('APP_DEBUG')
                ? ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                : 'Task execution failed';

            return new TaskResulted($errorDetails, false);
        }
    }

    /**
     * Получение контейнера состояния
     */
    public function getStateContainer(): StateContainerWrapper {
        return $this->stateContainer;
    }
}