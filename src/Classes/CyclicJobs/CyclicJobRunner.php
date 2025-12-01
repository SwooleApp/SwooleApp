<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Swoole\Coroutine;

class CyclicJobRunner
{
    /**
     * @var CyclicJobsInterface[]
     */
    private iterable $jobs;

    /**
     * @param CyclicJobsInterface[] $jobs
     */
    public function __construct(iterable $jobs) {
        $this->jobs = $jobs;
    }

    public function start(): void {
        foreach ($this->jobs as $job) {
            go(function () use ($job) {
                Coroutine::sleep($job->getStartupSleepSecond());
                // @phpstan-ignore-next-line
                while (true) {
                    $job->runJob();
                    Coroutine::sleep($job->getTimeSleepSecond());
                }
            });
        }
    }

}