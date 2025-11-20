<?php
namespace tests\Classes\CyclicJobs;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob;
use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob
 */
class AbstractCyclicJobTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::getTimeSleepSecond
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::getStartupSleepSecond
     */
    public function testAbstractCyclicJobDefaultValues()
    {
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $job = $this->getMockForAbstractClass(
            AbstractCyclicJob::class,
            [$application, $server]
        );

        $this->assertEquals(86400.0, $job->getTimeSleepSecond());
        $this->assertEquals(10.0, $job->getStartupSleepSecond());
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::__construct
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::getTimeSleepSecond
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::getStartupSleepSecond
     */
    public function testAbstractCyclicJobInheritance()
    {
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $job = new TestConcreteCyclicJob($application, $server);

        $this->assertInstanceOf(AbstractCyclicJob::class, $job);
        $this->assertEquals(300.0, $job->getTimeSleepSecond());
        $this->assertEquals(30.0, $job->getStartupSleepSecond());
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\CyclicJobs\AbstractCyclicJob::__construct
     */
    public function testAbstractCyclicJobApplicationAndServerInjection()
    {
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $job = new TestConcreteCyclicJob($application, $server);

        // Проверяем через рефлексию, что зависимости установлены
        $reflection = new \ReflectionClass($job);

        $appProperty = $reflection->getProperty('application');
        $appProperty->setAccessible(true);
        $this->assertSame($application, $appProperty->getValue($job));

        $serverProperty = $reflection->getProperty('server');
        $serverProperty->setAccessible(true);
        $this->assertSame($server, $serverProperty->getValue($job));
    }
}

class TestConcreteCyclicJob extends AbstractCyclicJob
{
    protected float $timeSleep = 300.0;
    protected float $startupSleep = 30.0;

    public function runJob(): void
    {
        // Конкретная реализация для теста
    }
}