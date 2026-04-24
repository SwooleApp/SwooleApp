<?php

declare(strict_types=1);

namespace Sidalex\SwooleApp\Classes\Events;

class EventContext
{
    private ?\Swoole\Http\Request $request = null;
    private ?\Swoole\Http\Response $response = null;
    private ?int $workerId = null;
    private ?int $taskId = null;
    private ?int $reactorId = null;
    private mixed $taskData = null;
    private mixed $taskResult = null;
    private ?int $fd = null;
    private bool $isTaskWorker = false;
    private ?string $eventName = null;

    public static function forWorkerStart(\Swoole\Http\Server $server, int $workerId): self
    {
        $context = new self();
        $context->workerId = $workerId;
        $context->isTaskWorker = $server->taskworker;
        return $context;
    }

    public static function forWorkerStop(\Swoole\Http\Server $server, int $workerId): self
    {
        $context = new self();
        $context->workerId = $workerId;
        $context->isTaskWorker = $server->taskworker;
        return $context;
    }

    public static function forRequest(
        \Swoole\Http\Request $request,
        \Swoole\Http\Response $response
    ): self {
        $context = new self();
        $context->request = $request;
        $context->response = $response;
        return $context;
    }

    public static function forTask(
        \Swoole\Http\Server $server,
        int $taskId,
        int $reactorId,
        mixed $data
    ): self {
        $context = new self();
        $context->taskId = $taskId;
        $context->reactorId = $reactorId;
        $context->taskData = $data;
        $context->isTaskWorker = $server->taskworker;
        return $context;
    }

    public static function forTaskWithResult(
        \Swoole\Http\Server $server,
        int $taskId,
        int $reactorId,
        mixed $data,
        mixed $result
    ): self {
        $context = self::forTask($server, $taskId, $reactorId, $data);
        $context->taskResult = $result;
        return $context;
    }

    public static function forConnect(\Swoole\Http\Server $server, int $fd, int $reactorId): self
    {
        $context = new self();
        $context->fd = $fd;
        $context->reactorId = $reactorId;
        return $context;
    }

    public static function forClose(\Swoole\Http\Server $server, int $fd, int $reactorId): self
    {
        $context = new self();
        $context->fd = $fd;
        $context->reactorId = $reactorId;
        return $context;
    }

    public function setEventName(string $eventName): self
    {
        $this->eventName = $eventName;
        return $this;
    }

    public function getEventName(): ?string
    {
        return $this->eventName;
    }

    public function setTaskResult(mixed $result): self
    {
        $this->taskResult = $result;
        return $this;
    }

    public function getRequest(): ?\Swoole\Http\Request
    {
        return $this->request;
    }

    public function getResponse(): ?\Swoole\Http\Response
    {
        return $this->response;
    }

    public function getWorkerId(): ?int
    {
        return $this->workerId;
    }

    public function getTaskId(): ?int
    {
        return $this->taskId;
    }

    public function getReactorId(): ?int
    {
        return $this->reactorId;
    }

    public function getTaskData(): mixed
    {
        return $this->taskData;
    }

    public function getTaskResult(): mixed
    {
        return $this->taskResult;
    }

    public function getFd(): ?int
    {
        return $this->fd;
    }

    public function isTaskWorker(): bool
    {
        return $this->isTaskWorker;
    }
}