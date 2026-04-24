<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

interface EventHandlerConfigInterface
{
    public function getServer(): \Swoole\Http\Server;

    public function getWorkerId(): int;

    public function isTaskWorker(): bool;

    public function getRequest(): ?\Swoole\Http\Request;

    public function getResponse(): ?\Swoole\Http\Response;

    public function getTaskId(): ?int;

    public function getReactorId(): ?int;

    public function getTaskData(): mixed;

    public function getTaskResult(): mixed;

    public function getFd(): ?int;
}