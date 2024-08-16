<?php

namespace Sidalex\SwooleApp\Classes\Tasks\Executors;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;

interface TaskExecutorInterface
{
    /**
     * @param \Swoole\Http\Server $server
     * @param int $taskId
     * @param int $reactorId
     * @param TaskDataInterface $data
     */
    public function __construct(\Swoole\Http\Server $server, int $taskId, int $reactorId, TaskDataInterface $data, Application $app);

    /**
     * @return TaskResulted
     */
    public function execute(): TaskResulted;

}