<?php
namespace tests;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Tasks\Data\BasicTaskData;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Application
 * @covers \Sidalex\SwooleApp\Classes\Tasks\TaskResulted
 */
class ApplicationTaskExecuteTest extends TestCase
{
    private Application $app;
    private Server $serverMock;
    private \stdClass $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new \stdClass();
        $this->config->app_debug = true;
        $this->config->controllers = []; // Добавляем пустой массив controllers
        $this->app = new Application($this->config);
        $this->serverMock = $this->createMock(Server::class);
    }

    /**
     * @test
     * Test checks successful task execution when:
     * - Executor class exists
     * - Class implements TaskExecutorInterface
     * - Task returns valid TaskResulted
     */
    public function testSuccessfulTaskExecution(): void
    {
        $taskData = new BasicTaskData(
            SuccessfulTestExecutor::class,
            ['test' => 'data']
        );

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getResult());
    }

    /**
     * @test
     * Test checks handling of non-existent executor class.
     * Expected to fail with appropriate error message.
     */
    public function testNonExistentExecutorClass(): void
    {
        $taskData = new BasicTaskData(
            'NonExistentClass',
            ['test' => 'data']
        );

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertFalse($result->isSuccess());
        $this->expectException(\Sidalex\SwooleApp\Classes\Tasks\TaskException::class);
        $this->assertStringContainsString('not found', $result->getResult());
    }

    /**
     * @test
     * Test checks handling of class that exists but doesn't implement
     * required TaskExecutorInterface. Expected to fail with interface error.
     */
    public function testClassWithoutInterface(): void
    {
        $taskData = new BasicTaskData(
            InvalidTestExecutor::class,
            ['test' => 'data']
        );

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertFalse($result->isSuccess());
        $this->expectException(\Sidalex\SwooleApp\Classes\Tasks\TaskException::class);
        $this->assertStringContainsString('must implement TaskExecutorInterface', $result->getResult());
    }

    /**
     * @test
     * Test checks handling of empty task class name.
     * Expected to fail with "empty class name" error.
     */
    public function testEmptyTaskClassName(): void
    {
        $taskData = new BasicTaskData('', ['test' => 'data']);

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertFalse($result->isSuccess());
        $this->expectException(\Sidalex\SwooleApp\Classes\Tasks\TaskException::class);
        $this->assertStringContainsString('Task class name is empty', $result->getResult());
    }

    /**
     * @test
     * Test checks error handling in production mode (app_debug = false).
     * Expected generic error message without details.
     */
    public function testProductionModeErrorHandling(): void
    {
        $this->config->app_debug = false;
        $taskData = new BasicTaskData(
            'NonExistentClass',
            ['test' => 'data']
        );

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertFalse($result->isSuccess());
        $this->expectException(\Sidalex\SwooleApp\Classes\Tasks\TaskException::class);
        $this->assertEquals('Task execution failed', $result->getResult());
    }
}

class SuccessfulTestExecutor implements \Sidalex\SwooleApp\Classes\Tasks\Executors\TaskExecutorInterface
{
    public function __construct(
        \Swoole\Http\Server $server,
        int $taskId,
        int $reactorId,
        \Sidalex\SwooleApp\Classes\Tasks\Data\TaskDataInterface $data,
        \Sidalex\SwooleApp\Application $app
    ) {
    }

    public function execute(): \Sidalex\SwooleApp\Classes\Tasks\TaskResulted
    {
        return new \Sidalex\SwooleApp\Classes\Tasks\TaskResulted('success');
    }
}

class InvalidTestExecutor
{
    // Не реализует TaskExecutorInterface
}