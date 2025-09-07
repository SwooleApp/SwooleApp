<?php

namespace Sidalex\SwooleApp\Classes\Builder;

use DI\Container;
use DI\ContainerBuilder as PHPDIContainerBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Exception;

class DIContainerBuilder
{
    private ConfigWrapper $config;

    public function __construct(ConfigWrapper $config)
    {
        $this->config = $config;
    }

    public function build(): Container
    {
        $containerBuilder = new PHPDIContainerBuilder();


        $diConfig = $this->config->getConfigFromKey('di') ?? new \stdClass();


        $compilationPath = $diConfig->compilation_path ?? null;
        if ($compilationPath && is_string($compilationPath)) {
            $containerBuilder->enableCompilation($compilationPath);
        }

        $definitions = $diConfig->definitions ?? [];
        if (!empty($definitions)) {
            foreach ($definitions as $definitionFile) {
                if (file_exists($definitionFile)) {
                    $containerBuilder->addDefinitions($definitionFile);
                }
            }
        }

        $services = $diConfig->services ?? [];
        if (!empty($services)) {
            $servicesDefinitions = [];
            foreach ($services as $serviceName => $serviceConfig) {
                $servicesDefinitions[$serviceName] = \DI\create($serviceConfig->class)
                    ->constructor(...($serviceConfig->arguments ?? []));
            }
            $containerBuilder->addDefinitions($servicesDefinitions);
        }

        $containerBuilder->useAutowiring(true);

        try {
            return $containerBuilder->build();
        } catch (Exception $e) {
            throw new \RuntimeException("Failed to build DI container: " . $e->getMessage());
        }
    }
}