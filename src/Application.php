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
use Sidalex\SwooleApp\Classes\Initiation\StateContainerInitiationInterface;
use Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Sidalex\SwooleApp\Classes\Wrapper\StateContainerWrapper;
use Swoole\Coroutine;
use Swoole\Http\Server;


class Application
{
    protected ConfigWrapper $config;
    /**
     * @var array<mixed>
     */
    protected array $routesCollection;

    protected StateContainerWrapper $stateContainer;
    /**
     * @var array<array{class: string, options: array<mixed>}>
     */
    protected array $globalMiddlewares = [];

    /**
     * @param \stdClass|null $baseConfig
     * @param string[] $configValidators
     * @param ConfigBuilder|null $configBuilder
     * @param RoutesCollectionBuilder|null $routesCollectionBuilder
     * @throws \ReflectionException
     */
    public function __construct(
        ?\stdClass                $baseConfig = null,
        array                    $configValidators = [],
        ?ConfigBuilder           $configBuilder = null,
        ?RoutesCollectionBuilder $routesCollectionBuilder = null,
    )
    {
        try {
            $loader = $configBuilder ?? new ConfigBuilder($baseConfig);
            if (!empty($configValidators) && !$loader->validate($configValidators)) {
                throw new \RuntimeException(
                    "Configuration validation failed:\n" .
                    implode("\n", $loader->getErrors())
                );
            }

            $this->config = new ConfigWrapper($loader->getConfig());
            $this->initGlobalMiddlewares();
            $this->initializeRoutes($routesCollectionBuilder);
            $this->initializeStateContainer();

        } catch (\Exception $e) {
            error_log('Application initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param RoutesCollectionBuilder|null $routesCollectionBuilder
     * @return void
     * @throws \ReflectionException
     */
    private function initializeRoutes(?RoutesCollectionBuilder $routesCollectionBuilder): void
    {
        $routeBuilder = $routesCollectionBuilder ?? new RoutesCollectionBuilder($this->config);
        $this->routesCollection = $routeBuilder->buildRoutesCollection();
    }

    /**
     * @return void
     */
    private function initializeStateContainer(): void
    {
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


    protected function initGlobalMiddlewares(): void
    {
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
    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function getRoutesCollection(): array
    {
        return $this->routesCollection;
    }

    public function execute(\Swoole\Http\Request $request, \Swoole\Http\Response $response, Server $server): void
    {
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

    public function getConfig(): ConfigWrapper
    {
        return $this->config;
    }

    public function taskExecute(\Swoole\Http\Server $server, int $taskId, int $reactorId, TaskDataInterface $data): TaskResulted
    {
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
            // Логирование ошибки (можно добавить зависимость от PSR-3 LoggerInterface)
            if ($this->config->getConfigFromKey('APP_DEBUG')) {
                error_log("Task execution failed: " . $e->getMessage());
            }

            // Возвращаем подробную информацию об ошибке в debug режиме
            $errorDetails = $this->config->getConfigFromKey('APP_DEBUG')
                ? ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                : 'Task execution failed';

            return new TaskResulted($errorDetails, false);
        }
    }

    public function initCyclicJobs(Server $server): void
    {
        $builder = new CyclicJobsBuilder($this->config);
        $listCyclicJobs = $builder->buildCyclicJobs($this, $server);
        $cyclicJobRunner = new CyclicJobRunner($listCyclicJobs);
        $cyclicJobRunner->start();
        unset($builder);
        unset($listCyclicJobs);
    }

    /**
     * @return StateContainerWrapper
     */
    public function getStateContainer(): StateContainerWrapper
    {
        return $this->stateContainer;
    }

}