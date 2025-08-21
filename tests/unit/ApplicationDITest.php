<?php

namespace tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * Unit tests for Dependency Injection functionality in the Application
 * Tests DI container integration, service registration, and custom builder usage
 *
 * @covers \Sidalex\SwooleApp\Application
 * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder
 * @covers \Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper
 */
class ApplicationDITest extends TestCase
{
    /**
     * Tests that application creates and returns a DI container instance
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::getDIContainer
     */
    public function testApplicationHasDIContainer()
    {
        $config = new \stdClass();
        $config->controllers = [];

        $app = new Application($config);

        $this->assertInstanceOf(Container::class, $app->getDIContainer());
    }

    /**
     * Tests that DI container contains Application and ConfigWrapper instances
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::getDIContainer
     * @covers \Sidalex\SwooleApp\Application::getConfig
     */
    public function testDIContainerContainsApplicationAndConfig()
    {
        $config = new \stdClass();
        $config->controllers = [];

        $app = new Application($config);
        $container = $app->getDIContainer();

        $this->assertSame($app, $container->get(Application::class));
        $this->assertSame($app->getConfig(), $container->get(ConfigWrapper::class));
    }

    /**
     * Tests the ability to use custom DI container builder
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::getDIContainer
     */
    public function testCustomDIContainerBuilder()
    {
        $config = new \stdClass();
        $config->controllers = [];

        $mockContainer = $this->createMock(Container::class);

        $mockBuilder = $this->createMock(DIContainerBuilder::class);
        $mockBuilder->expects($this->once())
            ->method('build')
            ->willReturn($mockContainer);

        $app = new Application($config, [], null, null, $mockBuilder);

        $this->assertSame($mockContainer, $app->getDIContainer());
    }

    /**
     * Tests DI container configuration with various settings
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::getDIContainer
     * @covers \Sidalex\SwooleApp\Classes\Builder\DIContainerBuilder::build
     */
    public function testDIContainerConfiguration()
    {
        $config = new \stdClass();
        $config->controllers = [];
        $config->di = new \stdClass();
        $config->di->compilation_path = '/tmp/phpdi_test';
        $config->di->definitions = [];
        $config->di->services = new \stdClass();

        $app = new Application($config);
        $container = $app->getDIContainer();

        // Container should be properly configured
        $this->assertInstanceOf(Container::class, $container);
    }
}