<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

interface SwooleEventHandlersRegistryInterface
{
    /**
     * @param array<int, array{class: string, method: string}> $handlers
     */
    public function registerHandlers(string $eventName, string $phase, array $handlers): void;

    /**
     * @return array<int, array{class: string, method: string}>
     */
    public function getHandlers(string $eventName, string $phase): array;

    public function hasHandlers(string $eventName, string $phase): bool;
}