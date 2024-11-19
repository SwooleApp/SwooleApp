<?php

namespace Sidalex\SwooleApp;

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
     * @param \stdClass $configPath
     * @param string[] $ConfigValidationList
     */
    public function __construct(\stdClass $configPath, array $ConfigValidationList = [])
    {
        try {
            foreach ($ConfigValidationList as $configValidationClassName) {
                $validationClass = new $configValidationClassName;
                if ($validationClass instanceof ConfigValidatorInterface) {
                    $validationClass->validate($configPath);
                } else {
                    //todo: add logic to logs inition not ConfigValidatorInterface validation class
                }
            }
            $this->config = new ConfigWrapper($configPath);
            $Route_builder = new RoutesCollectionBuilder($this->config);
            $this->routesCollection = $Route_builder->buildRoutesCollection();
            if (
                !empty($this->config->getConfigFromKey('StateContainerInitiation'))
                && is_array($this->config->getConfigFromKey('StateContainerInitiation'))
            ) {
                $classStateContainer = new \stdClass();
                foreach ($this->config->getConfigFromKey('StateContainerInitiation') as $classStateInitiator) {
                    if (Utilities::classImplementInterface(
                        $classStateInitiator,
                        'Sidalex\SwooleApp\Classes\Initiation\StateContainerInitiationInterface'
                    )) {
                        $classStateInitiatorObject = new $classStateInitiator();
                        if($classStateInitiatorObject instanceof StateContainerInitiationInterface) {
                            $classStateInitiatorObject->init($this);
                            $classStateContainer->{$classStateInitiatorObject->getKey()} = $classStateInitiatorObject->getResultInitiation();
                            unset($classStateInitiatorObject);
                        }
                    }
                }
                $this->stateContainer = new StateContainerWrapper($classStateContainer);
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            exit(1);
        }
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function getRoutesCollection(): array
    {
        return $this->routesCollection;
    }

    public function execute(\Swoole\Http\Request $request, \Swoole\Http\Response $response, Server $server) :void
    {
        $Route_builder = new RoutesCollectionBuilder($this->config);
        $itemRouteCollection = $Route_builder->searchInRoute($request, $this->routesCollection);
        if (empty($itemRouteCollection)) {
            $controller = (new NotFoundControllerBuilder($request, $response, $this->config))->build();
        } else {
            $controller = $Route_builder->getController($itemRouteCollection, $request, $response);
        }
        $controller->setApplication($this, $server);
        $response = $controller->execute();
        unset($controller);
    }

    public function getConfig(): ConfigWrapper
    {
        return $this->config;
    }

    public function taskExecute(\Swoole\Http\Server $server, int $taskId, int $reactorId, TaskDataInterface $data): TaskResulted
    {
        $TaskExecutorClassName = $data->getTaskClassName();
        if (Utilities::classImplementInterface($TaskExecutorClassName, 'Sidalex\SwooleApp\Classes\Tasks\Executors\TaskExecutorInterface')) {
            $TaskExecutorClass = new $TaskExecutorClassName($server, $taskId, $reactorId, $data, $this);
            if ($TaskExecutorClass instanceof TaskExecutorInterface) {
                $result = $TaskExecutorClass->execute();
                unset($TaskExecutorClass);
            } else {
                return new TaskResulted('error task Executor not implemented TaskExecutorInterface', false);
            }
        } else {
            return new TaskResulted('error task Executor not implemented TaskExecutorInterface', false);
        }
        if (!($result instanceof TaskResulted)) {
            return new TaskResulted('error result is not a TaskResulted', false);
        }
        return $result;
    }

    public function initCyclicJobs(Server $server): void
    {
        $app = $this;
        $builder = new CyclicJobsBuilder($this->config);
        $listCyclicJobs = $builder->buildCyclicJobs($app, $server);
        foreach ($listCyclicJobs as $job)
            // @phpstan-ignore-next-line
            go(function () use ($app, $job) {
                // @phpstan-ignore-next-line
                while (true) {
                    Coroutine::sleep($job->getTimeSleepSecond());
                    $job->runJob();
                }
            });
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