<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

class SwooleEventHandlersRegistry implements SwooleEventHandlersRegistryInterface
{
    /** @var array<mixed> */
    private array $handlers = [];

    public function registerHandlers(string $eventName, string $phase, array $handlers): void
    {
        $this->handlers[$eventName][$phase] = $handlers;
    }

    /**
     * @return array<int, array{class: string, method: string, callable?: callable}>
     */
    public function getHandlers(string $eventName, string $phase): array
    {
        return $this->handlers[$eventName][$phase] ?? [];
    }

    public function hasHandlers(string $eventName, string $phase): bool
    {
        return !empty($this->handlers[$eventName][$phase]);
    }
}