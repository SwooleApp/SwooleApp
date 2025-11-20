<?php
namespace tests\Classes\CyclicJobs;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder;
use Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsInterface;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder
 */
class CyclicJobsBuilderTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::buildCyclicJobs
     */
    public function testBuildCyclicJobsWithEmptyConfig()
    {
        $config = new \stdClass();
        $config->CyclicJobs = null;

        $configWrapper = new ConfigWrapper($config);
        $builder = new CyclicJobsBuilder($configWrapper);

        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $jobs = $builder->buildCyclicJobs($application, $server);

        $this->assertIsArray($jobs);
        $this->assertEmpty($jobs);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::buildCyclicJobs
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::initListClassName
     */
    public function testBuildCyclicJobsWithValidJobs()
    {
        $config = new \stdClass();
        $config->CyclicJobs = [
            TestCyclicJob1::class,
            TestCyclicJob2::class
        ];

        $configWrapper = new ConfigWrapper($config);
        $builder = new CyclicJobsBuilder($configWrapper);

        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $jobs = $builder->buildCyclicJobs($application, $server);

        $this->assertCount(2, $jobs);
        $this->assertInstanceOf(TestCyclicJob1::class, $jobs[0]);
        $this->assertInstanceOf(TestCyclicJob2::class, $jobs[1]);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::buildCyclicJobs
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::initListClassName
     */
    public function testBuildCyclicJobsWithInvalidJobs()
    {
        $config = new \stdClass();
        $config->CyclicJobs = [
            TestCyclicJob1::class,
            \stdClass::class, // Не реализует интерфейс
            TestCyclicJob2::class
        ];

        $configWrapper = new ConfigWrapper($config);
        $builder = new CyclicJobsBuilder($configWrapper);

        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $jobs = $builder->buildCyclicJobs($application, $server);

        // Должны быть созданы только валидные jobs
        $this->assertCount(2, $jobs);
        $this->assertInstanceOf(TestCyclicJob1::class, $jobs[0]);
        $this->assertInstanceOf(TestCyclicJob2::class, $jobs[1]);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::buildCyclicJobs
     */
    public function testBuildCyclicJobsWithNonArrayConfig()
    {
        $config = new \stdClass();
        $config->CyclicJobs = "invalid_config"; // Не массив

        $configWrapper = new ConfigWrapper($config);
        $builder = new CyclicJobsBuilder($configWrapper);

        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $jobs = $builder->buildCyclicJobs($application, $server);

        $this->assertIsArray($jobs);
        $this->assertEmpty($jobs);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::buildCyclicJobs
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\CyclicJobsBuilder::initListClassName
     */
    public function testBuildCyclicJobsWithEmptyArray()
    {
        $config = new \stdClass();
        $config->CyclicJobs = [];

        $configWrapper = new ConfigWrapper($config);
        $builder = new CyclicJobsBuilder($configWrapper);

        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $jobs = $builder->buildCyclicJobs($application, $server);

        $this->assertIsArray($jobs);
        $this->assertEmpty($jobs);
    }
}

class TestCyclicJob1 implements CyclicJobsInterface
{
    private Application $application;
    private Server $server;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        return 60.0;
    }

    public function runJob(): void
    {
        // Тестовая реализация
    }

    public function getStartupSleepSecond(): float
    {
        return 10.0;
    }
}

class TestCyclicJob2 implements CyclicJobsInterface
{
    private Application $application;
    private Server $server;

    public function __construct(Application $application, Server $server)
    {
        $this->application = $application;
        $this->server = $server;
    }

    public function getTimeSleepSecond(): float
    {
        return 3600.0;
    }

    public function runJob(): void
    {
        // Тестовая реализация
    }

    public function getStartupSleepSecond(): float
    {
        return 5.0;
    }
}