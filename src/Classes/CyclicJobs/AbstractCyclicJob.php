<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

abstract class AbstractCyclicJob implements CyclicJobsInterface
{
    protected Application $application;
    protected Server $server;
    protected float $timeSleep = 86400;
    protected float $startupSleep = 10;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        return $this->timeSleep;
    }

    public function getStartupSleepSecond(): float
    {
        return $this->startupSleep;
    }

    abstract public function runJob(): void;
}