<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

class EventHandlerExecutor implements EventHandlerExecutorInterface
{
    private SwooleEventHandlersRegistry $registry;
    private Application $application;

    public function __construct(Application $application, SwooleEventHandlersRegistry $registry)
    {
        $this->application = $application;
        $this->registry = $registry;
    }

    public function executeBeforeHandlers(Server $server, EventContext $context): void
    {
        $this->executeHandlers('before', $server, $context);
    }

    public function executeAfterHandlers(Server $server, EventContext $context): void
    {
        $this->executeHandlers('after', $server, $context);
    }

    private function executeHandlers(string $phase, Server $server, EventContext $context): void
    {
        $eventName = $context->getEventName();
        if ($eventName === null) {
            return;
        }

        $handlers = $this->registry->getHandlers($eventName, $phase);

        foreach ($handlers as $handler) {
            $this->executeHandler($handler, $server, $context);
        }
    }

    /**
     * @param array{class: string, method: string, callable?: callable} $handler
     */
    private function executeHandler(array $handler, Server $server, EventContext $context): void
    {
        try {
            if (isset($handler['callable'])) {
                $this->executeCallable($handler['callable'], $server, $context);
                return;
            }

            $instance = new $handler['class']();
            $method = $handler['method'];

            $this->executeMethod($instance, $method, $server, $context);
        } catch (\Throwable $e) {
            error_log(
                sprintf(
                    "Event handler error in %s: %s",
                    $context->getEventName(),
                    $e->getMessage()
                )
            );
        }
    }

    private function executeCallable(callable $callable, Server $server, EventContext $context): void
    {
        $args = $this->buildHandlerArgs($server, $context);
        $callable(...$args);
    }

    private function executeMethod(object $instance, string $method, Server $server, EventContext $context): void
    {
        $args = $this->buildHandlerArgs($server, $context);
        $instance->$method(...$args);
    }

    /**
     * @return array<mixed>
     */
    private function buildHandlerArgs(Server $server, EventContext $context): array
    {
        $args = [];

        switch ($context->getEventName()) {
            case 'workerStart':
            case 'workerStop':
                $args = [$this->application, $server, $context->getWorkerId()];
                break;
            case 'request':
                $args = [$this->application, $context->getRequest(), $context->getResponse()];
                break;
            case 'task':
                $args = [
                    $this->application,
                    $server,
                    $context->getTaskId(),
                    $context->getReactorId(),
                    $context->getTaskData(),
                ];
                if ($context->getTaskResult() !== null) {
                    $args[] = $context->getTaskResult();
                }
                break;
            case 'connect':
            case 'close':
                $args = [$this->application, $server, $context->getFd(), $context->getReactorId()];
                break;
        }

        return $args;
    }
}