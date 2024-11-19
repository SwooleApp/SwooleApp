<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Swoole\Http\Server;

class CyclicJobsBuilder
{
    /**
     * @var string[]
     */
    private array $listClassName = [];

    public function __construct(ConfigWrapper $configWrapper)
    {
        $listClassName = $configWrapper->getConfigFromKey('CyclicJobs');
        if (!is_null($listClassName) && is_array($listClassName)) {
            $this->initListClassName($listClassName);
        }
    }

    /**
     * @param string[] $listClassName
     * @return void
     */
    private function initListClassName(array $listClassName): void
    {
        foreach ($listClassName as $className) {
            if (
                Utilities::classImplementInterface(
                    $className,
                    "Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface"
                )
            ) {
                $this->listClassName[] = $className;
            } else {
                //todo: add log fatal information
            }
        }
    }

    /**
     * @param Application $application
     * @return array<int<0,max>,CyclicJobsInterface>
     */
    public function buildCyclicJobs(Application $application, Server $server): array
    {
        $cyclicJobs = [];
        foreach ($this->listClassName as $className) {
            $cyclicJob = new $className($application, $server);
            if ($cyclicJob instanceof CyclicJobsInterface) {
                $cyclicJobs[] = new $className($application, $server);
            } else {
                //todo: add log fatal information
            }
        }
        // @phpstan-ignore-next-line
        return $cyclicJobs;
    }
}