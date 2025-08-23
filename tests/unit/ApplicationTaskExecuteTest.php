<?php
namespace tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Tasks\Data\BasicTaskData;
use Sidalex\SwooleApp\Classes\Tasks\TaskResulted;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Application::taskExecute
 * @covers \Sidalex\SwooleApp\Classes\Tasks\TaskResulted
 * @covers \Sidalex\SwooleApp\Classes\Tasks\Data\BasicTaskData
 */
class ApplicationTaskExecuteTest extends TestCase
{
    private Application $app;
    private Server $serverMock;
    private \stdClass $config;
    private Container $containerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new \stdClass();
        $this->config->app_debug = true;
        $this->config->controllers = [];

        $this->containerMock = $this->createMock(Container::class);

        // Create real Application instance but mock the DI container
        $this->app = new Application($this->config);

        // Use reflection to inject the mocked container
        $reflection = new \ReflectionClass($this->app);
        $property = $reflection->getProperty('diContainer');
        $property->setAccessible(true);
        $property->setValue($this->app, $this->containerMock);

        $this->serverMock = $this->createMock(Server::class);
    }

    /**
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testSuccessfulTaskExecution(): void
    {
        $taskData = new BasicTaskData(
            SuccessfulTestExecutor::class,
            ['test' => 'data']
        );

        $mockExecutor = $this->createMock(SuccessfulTestExecutor::class);
        $mockExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(new TaskResulted('success'));

        $this->containerMock->expects($this->once())
            ->method('make')
            ->with(
                SuccessfulTestExecutor::class,
                [
                    'server' => $this->serverMock,
                    'taskId' => 1,
                    'reactorId' => 1,
                    'data' => $taskData,
                    'app' => $this->app
                ]
            )
            ->willReturn($mockExecutor);

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
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testNonExistentExecutorClass(): void
    {
        $taskData = new BasicTaskData(
            'NonExistentClass',
            ['test' => 'data']
        );

        $this->containerMock->expects($this->never())
            ->method('make');

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
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testClassWithoutInterface(): void
    {
        $taskData = new BasicTaskData(
            InvalidTestExecutor::class,
            ['test' => 'data']
        );

        $this->containerMock->expects($this->never())
            ->method('make');

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
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testEmptyTaskClassName(): void
    {
        $taskData = new BasicTaskData('', ['test' => 'data']);

        $this->containerMock->expects($this->never())
            ->method('make');

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
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testProductionModeErrorHandling(): void
    {
        $this->config->app_debug = false;
        $taskData = new BasicTaskData(
            'NonExistentClass',
            ['test' => 'data']
        );

        $this->containerMock->expects($this->never())
            ->method('make');

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

    /**
     * @covers \Sidalex\SwooleApp\Application::taskExecute
     */
    public function testTaskExecutorCreatedWithDIContainer(): void
    {
        $taskData = new BasicTaskData(
            SuccessfulTestExecutor::class,
            ['test' => 'data']
        );

        $mockExecutor = $this->createMock(SuccessfulTestExecutor::class);
        $mockExecutor->expects($this->once())
            ->method('execute')
            ->willReturn(new TaskResulted('success'));

        $this->containerMock->expects($this->once())
            ->method('make')
            ->with(
                SuccessfulTestExecutor::class,
                [
                    'server' => $this->serverMock,
                    'taskId' => 1,
                    'reactorId' => 1,
                    'data' => $taskData,
                    'app' => $this->app
                ]
            )
            ->willReturn($mockExecutor);

        $result = $this->app->taskExecute(
            $this->serverMock,
            1,
            1,
            $taskData
        );

        $this->assertTrue($result->isSuccess());
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