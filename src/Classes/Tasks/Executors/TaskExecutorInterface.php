<?php

namespace Sidalex\SwooleApp\Classes\Tasks\Executors;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Swoole\Http\Server;

interface TaskExecutorInterface {
    public function __construct(
        Server $server,
        int $taskId,
        int $reactorId,
        TaskDataInterface $data,
        Application $app
    );
    public function execute(): TaskResulted;
}