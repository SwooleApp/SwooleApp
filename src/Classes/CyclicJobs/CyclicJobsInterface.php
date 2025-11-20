<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

interface CyclicJobsInterface
{
    public function __construct(Application $application, Server $server);

    /**
     * A method that returns the delay time in seconds between the execution of a cyclic task.
     * @return float
     */
    public function getTimeSleepSecond(): float;

    /**
     *A method that performs a cyclic task. It does not accept parameters and does not return values. Instead, it performs the necessary actions inside the method.
     * @return void
     */
    public function runJob(): void;

    /**
     * A method that returns the delay time after start the Application of a cyclic task execution in seconds.
     * @return float
     */
    public function getStartupSleepSecond(): float;
}