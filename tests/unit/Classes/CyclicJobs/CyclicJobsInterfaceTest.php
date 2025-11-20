<?php
namespace tests\Classes\CyclicJobs;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface;
use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface
 */
class CyclicJobsInterfaceTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::getTimeSleepSecond
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::runJob
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::getStartupSleepSecond
     */
    public function testCyclicJobsInterfaceImplementation()
    {
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $job = new TestFullCyclicJob($application, $server);

        $this->assertInstanceOf(CyclicJobsInterface::class, $job);
        $this->assertEquals(120.0, $job->getTimeSleepSecond());
        $this->assertEquals(15.0, $job->getStartupSleepSecond());

        // Проверяем, что runJob выполняется без ошибок
        $job->runJob();

        // Проверяем, что job был выполнен (если в реализации есть такая функциональность)
        $this->assertTrue($job->isJobExecuted());
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::getTimeSleepSecond
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::runJob
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::getStartupSleepSecond
     */
    public function testCyclicJobsInterfaceContract()
    {
        $interfaceReflection = new \ReflectionClass(CyclicJobsInterface::class);
        $methods = $interfaceReflection->getMethods();

        $methodNames = array_map(function($method) {
            return $method->getName();
        }, $methods);

        $expectedMethods = [
            '__construct',
            'getTimeSleepSecond',
            'runJob',
            'getStartupSleepSecond'
        ];

        sort($methodNames);
        sort($expectedMethods);

        $this->assertEquals($expectedMethods, $methodNames);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::__construct
     */
    public function testCyclicJobsConstructorParameters()
    {
        $interfaceReflection = new \ReflectionClass(CyclicJobsInterface::class);
        $constructor = $interfaceReflection->getMethod('__construct');
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('application', $parameters[0]->getName());
        $this->assertEquals('server', $parameters[1]->getName());

        // Проверяем типы параметров
        $this->assertEquals(Application::class, $parameters[0]->getType()->getName());
        $this->assertEquals(Server::class, $parameters[1]->getType()->getName());
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface::runJob
     */
    public function testCyclicJobRunMethodExecutesWithoutErrors()
    {
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $job = new TestSimpleCyclicJob($application, $server);

        // Этот тест действительно не должен выполнять assertions в runJob
        // Мы просто проверяем, что метод выполняется без исключений
        $this->expectNotToPerformAssertions();

        $job->runJob();
    }
}

class TestFullCyclicJob implements CyclicJobsInterface
{
    private Application $application;
    private Server $server;
    private bool $jobExecuted = false;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        return 120.0;
    }

    public function runJob(): void
    {
        $this->jobExecuted = true;
        // Тестовая логика выполнения job
    }

    public function getStartupSleepSecond(): float
    {
        return 15.0;
    }

    public function isJobExecuted(): bool
    {
        return $this->jobExecuted;
    }
}

class TestSimpleCyclicJob implements CyclicJobsInterface
{
    public function __construct(Application $application, Server $server) {}

    public function getTimeSleepSecond(): float { return 60.0; }

    public function runJob(): void
    {
        // Простая реализация без side effects для тестирования
    }

    public function getStartupSleepSecond(): float { return 10.0; }
}