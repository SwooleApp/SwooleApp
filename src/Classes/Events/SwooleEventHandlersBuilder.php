<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

class SwooleEventHandlersBuilder
{
    private const VALID_EVENTS = [
        'workerStart',
        'workerStop',
        'request',
        'task',
        'connect',
        'close',
    ];
    private const VALID_PHASES = ['before', 'after'];

    private ConfigWrapper $config;

    public function __construct(ConfigWrapper $config)
    {
        $this->config = $config;
    }

    /**
     * @throws \RuntimeException
     */
    public function build(): SwooleEventHandlersRegistry
    {
        $registry = new SwooleEventHandlersRegistry();
        $eventsConfig = $this->config->getConfigFromKey('events');

        if (empty($eventsConfig)) {
            return $registry;
        }

        $this->validateEventsConfig($eventsConfig);

        foreach (self::VALID_EVENTS as $eventName) {
            if (!isset($eventsConfig[$eventName])) {
                continue;
            }

            $eventHandlers = $eventsConfig[$eventName];

            foreach (self::VALID_PHASES as $phase) {
                if (!isset($eventHandlers[$phase])) {
                    continue;
                }

                $handlers = $this->processHandlers($eventName, $phase, $eventHandlers[$phase]);
                $registry->registerHandlers($eventName, $phase, $handlers);
            }
        }

        return $registry;
    }

    /**
     * @param array<mixed> $eventsConfig
     * @throws \RuntimeException
     */
    private function validateEventsConfig(array $eventsConfig): void
    {
        foreach ($eventsConfig as $eventName => $eventHandlers) {
            if (!in_array($eventName, self::VALID_EVENTS, true)) {
                throw new \RuntimeException(
                    "Invalid event '{$eventName}'. Valid events: " . implode(', ', self::VALID_EVENTS)
                );
            }

            if (!is_array($eventHandlers)) {
                throw new \RuntimeException("Event '{$eventName}' must be an array");
            }

            foreach (self::VALID_PHASES as $phase) {
                if (isset($eventHandlers[$phase])) {
                    $this->validatePhase($eventName, $phase, $eventHandlers[$phase]);
                }
            }
        }
    }

    /**
     * @param array<mixed> $phaseHandlers
     * @throws \RuntimeException
     */
    private function validatePhase(string $eventName, string $phase, array $phaseHandlers): void
    {
        if (!is_array($phaseHandlers)) {
            throw new \RuntimeException(
                "Event '{$eventName}.{$phase}' must be an array"
            );
        }

        $priorities = array_keys($phaseHandlers);
        $uniquePriorities = array_unique($priorities);

        if (count($priorities) !== count($uniquePriorities)) {
            $duplicates = array_diff_assoc($priorities, $uniquePriorities);
            throw new \RuntimeException(
                sprintf(
                    "Duplicate priority %d in '%s.%s' event handlers",
                    array_values($duplicates)[0],
                    $eventName,
                    $phase
                )
            );
        }

        foreach ($phaseHandlers as $priority => $handler) {
            $this->validateHandler($eventName, $phase, $priority, $handler);
        }
    }

    /**
     * @param mixed $handler
     * @throws \RuntimeException
     */
    private function validateHandler(
        string $eventName,
        string $phase,
        int $priority,
        mixed $handler
    ): void {
        if (is_callable($handler)) {
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!is_string($class) || !is_string($method)) {
                throw new \RuntimeException(
                    "Invalid handler format in '{$eventName}.{$phase}' priority {$priority}"
                );
            }

            if (!class_exists($class)) {
                throw new \RuntimeException(
                    "Handler class '{$class}' not found for event '{$eventName}'"
                );
            }

            if (!method_exists($class, $method)) {
                throw new \RuntimeException(
                    "Handler method '{$class}::{$method}' not found for event '{$eventName}'"
                );
            }

            return;
        }

        throw new \RuntimeException(
            "Invalid handler in '{$eventName}.{$phase}' priority {$priority}. " .
            "Expected callable or [Class::class, 'method']"
        );
    }

    /**
     * @param array<mixed> $phaseHandlers
     * @return array<int, array{class: string, method: string}>
     */
    private function processHandlers(
        string $eventName,
        string $phase,
        array $phaseHandlers
    ): array {
        $sorted = $phaseHandlers;
        ksort($sorted);

        $result = [];
        foreach ($sorted as $priority => $handler) {
            if (is_callable($handler)) {
                $result[$priority] = [
                    'class' => '',
                    'method' => '',
                    'callable' => $handler,
                ];
                continue;
            }

            [$class, $method] = $handler;
            $result[$priority] = [
                'class' => $class,
                'method' => $method,
            ];
        }

        return $result;
    }
}