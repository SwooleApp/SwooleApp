<?php

namespace tests\Classes\Builder;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * Unit tests for DIContainerBuilder class
 * Tests DI container construction with different configurations and services
 *
 * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder
 * @covers \Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper
 */
class DIContainerBuilderTest extends TestCase
{
    /**
     * Tests creation of basic DI container without additional configuration
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::build
     */
    public function testBuildBasicContainer()
    {
        $config = new \stdClass();
        $configWrapper = new ConfigWrapper($config);

        $builder = new DIContainerBuilder($configWrapper);
        $container = $builder->build();

        $this->assertInstanceOf(Container::class, $container);
    }

    /**
     * Tests DI container creation with service definitions
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::build
     */
    public function testBuildContainerWithServices()
    {
        $config = new \stdClass();
        $config->di = new \stdClass();
        $config->di->services = new \stdClass();

        // Mock service configuration
        $serviceConfig = new \stdClass();
        $serviceConfig->class = \stdClass::class;
        $serviceConfig->arguments = [];

        $config->di->services->TestService = $serviceConfig;

        $configWrapper = new ConfigWrapper($config);
        $builder = new DIContainerBuilder($configWrapper);
        $container = $builder->build();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertInstanceOf(\stdClass::class, $container->get('TestService'));
    }

    /**
     * Tests DI container creation with compilation enabled
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::build
     */
    public function testBuildContainerWithCompilation()
    {
        $config = new \stdClass();
        $config->di = new \stdClass();
        $config->di->compilation_path = sys_get_temp_dir() . '/phpdi_test';

        $configWrapper = new ConfigWrapper($config);
        $builder = new DIContainerBuilder($configWrapper);
        $container = $builder->build();

        $this->assertInstanceOf(Container::class, $container);

        // Clean up
        if (file_exists($config->di->compilation_path)) {
            array_map('unlink', glob($config->di->compilation_path . '/*'));
            rmdir($config->di->compilation_path);
        }
    }

    /**
     * Tests DI container creation with external definition files
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::build
     */
    public function testBuildContainerWithDefinitions()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'di_test');
        $definitionContent = <<<'PHP'
<?php
return [
    'test.service' => DI\create(stdClass::class),
];
PHP;
        file_put_contents($tempFile, $definitionContent);

        $config = new \stdClass();
        $config->di = new \stdClass();
        $config->di->definitions = [$tempFile];

        $configWrapper = new ConfigWrapper($config);
        $builder = new DIContainerBuilder($configWrapper);
        $container = $builder->build();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertInstanceOf(\stdClass::class, $container->get('test.service'));

        // Clean up
        unlink($tempFile);
    }
}