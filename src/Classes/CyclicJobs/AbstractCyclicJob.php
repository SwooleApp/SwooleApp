<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

abstract class AbstractCyclicJob implements CyclicJobsInterface
{
    protected Application $application;
    protected Server $server;
    protected float $timeSleep = 86400;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        return $this->timeSleep;
    }

    abstract public function runJob(): void;
}