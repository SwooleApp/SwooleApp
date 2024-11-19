<?php

namespace Sidalex\SwooleApp\Classes\Tasks\Executors;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Swoole\Http\Server;

interface TaskExecutorInterface
{
    /**
     * @param Server $server
     * @param int $taskId
     * @param int $reactorId
     * @param TaskDataInterface $data
     * @param Application $app
     */
    public function __construct(\Swoole\Http\Server $server, int $taskId, int $reactorId, TaskDataInterface $data, Application $app);

    /**
     * @return TaskResulted
     */
    public function execute(): TaskResulted;

}