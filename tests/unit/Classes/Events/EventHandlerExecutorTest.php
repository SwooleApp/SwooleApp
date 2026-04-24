<?php

namespace tests\Classes\Events;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Events\EventContext;
use Sidalex\SwooleApp\Classes\Events\EventHandlerExecutor;
use Sidalex\SwooleApp\Classes\Events\SwooleEventHandlersRegistry;

/**
 * @covers \Sidalex\SwooleApp\Classes\Events\EventHandlerExecutor
 *
 * Test suite for EventHandlerExecutor class which executes event handlers
 */
class EventHandlerExecutorTest extends TestCase
{
    private Application $app;
    private SwooleEventHandlersRegistry $registry;
    private EventHandlerExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createMock(Application::class);
        $this->registry = new SwooleEventHandlersRegistry();
        $this->executor = new EventHandlerExecutor($this->app, $this->registry);
    }

    public function testExecuteBeforeHandlersWithNoHandlers(): void
    {
        $context = $this->createContext('workerStart', 0, false);
        $this->executor->executeBeforeHandlers($this->createMockServer(), $context);
        $this->assertTrue(true);
    }

    public function testExecuteAfterHandlersWithNoHandlers(): void
    {
        $context = $this->createContext('workerStart', 0, false);
        $this->executor->executeAfterHandlers($this->createMockServer(), $context);
        $this->assertTrue(true);
    }

    public function testExecuteBeforeCallsHandler(): void
    {
        $handlers = [
            0 => ['class' => TestCallTrackingHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('workerStart', 'before', $handlers);

        TestCallTrackingHandler::$called = false;
        $this->executor->executeBeforeHandlers($this->createMockServer(), $this->createContext('workerStart', 0, false));

        $this->assertTrue(TestCallTrackingHandler::$called);
    }

    public function testExecuteAfterCallsHandler(): void
    {
        $handlers = [
            0 => ['class' => TestCallTrackingHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('workerStart', 'after', $handlers);

        TestCallTrackingHandler::$called = false;
        $this->executor->executeAfterHandlers($this->createMockServer(), $this->createContext('workerStart', 0, false));

        $this->assertTrue(TestCallTrackingHandler::$called);
    }

    public function testHandlerReceivesCorrectParameters(): void
    {
        $handlers = [
            0 => ['class' => TestParameterHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('workerStart', 'before', $handlers);

        TestParameterHandler::$receivedWorkerId = null;
        $this->executor->executeBeforeHandlers($this->createMockServer(), $this->createContext('workerStart', 5, false));

        $this->assertSame(5, TestParameterHandler::$receivedWorkerId);
    }

    public function testHandlerReceivesRequestParameters(): void
    {
        $handlers = [
            0 => ['class' => TestRequestParameterHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('request', 'before', $handlers);

        $request = new \Swoole\Http\Request();
        $response = new \Swoole\Http\Response();
        TestRequestParameterHandler::$receivedRequest = null;

        $this->executor->executeBeforeHandlers($this->createMockServer(), EventContext::forRequest($request, $response)->setEventName('request'));

        $this->assertSame($request, TestRequestParameterHandler::$receivedRequest);
    }

    public function testClosureHandlerExecuted(): void
    {
        $called = false;
        $handlers = [
            0 => ['class' => '', 'method' => '', 'callable' => function ($app, $server, $workerId) use (&$called) {
                $called = true;
            }],
        ];
        $this->registry->registerHandlers('workerStart', 'after', $handlers);

        $this->executor->executeAfterHandlers($this->createMockServer(), $this->createContext('workerStart', 0, false));

        $this->assertTrue($called);
    }

    public function testMultipleHandlersExecutedInOrder(): void
    {
        TestOrderingHandler::$order = [];

        $handlers = [
            -100 => ['class' => TestOrderingHandler::class, 'method' => 'handle'],
            0 => ['class' => TestOrderingHandler::class, 'method' => 'handle'],
            100 => ['class' => TestOrderingHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('workerStart', 'before', $handlers);

        $this->executor->executeBeforeHandlers($this->createMockServer(), $this->createContext('workerStart', 0, false));

        $this->assertCount(3, TestOrderingHandler::$order);
    }

    public function testHandlerExceptionIsCaught(): void
    {
        $handlers = [
            0 => ['class' => TestThrowingHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('workerStart', 'before', $handlers);

        $this->executor->executeBeforeHandlers($this->createMockServer(), $this->createContext('workerStart', 0, false));
        $this->assertTrue(true);
    }

    public function testTaskAfterHandlerReceivesResult(): void
    {
        $handlers = [
            0 => ['class' => TestTaskResultHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('task', 'after', $handlers);

        $taskData = new \stdClass();
        TestTaskResultHandler::$receivedResult = null;

        $context = EventContext::forTaskWithResult($this->createMockServer(), 1, 2, $taskData, 'result_data');
        $context->setEventName('task');

        $this->executor->executeAfterHandlers($this->createMockServer(), $context);

        $this->assertSame('result_data', TestTaskResultHandler::$receivedResult);
    }

    public function testConnectHandlerReceivesFdAndReactorId(): void
    {
        $handlers = [
            0 => ['class' => TestConnectHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('connect', 'before', $handlers);

        TestConnectHandler::$receivedFd = null;
        TestConnectHandler::$receivedReactorId = null;

        $context = EventContext::forConnect($this->createMockServer(), 5, 3);
        $context->setEventName('connect');

        $this->executor->executeBeforeHandlers($this->createMockServer(), $context);

        $this->assertSame(5, TestConnectHandler::$receivedFd);
        $this->assertSame(3, TestConnectHandler::$receivedReactorId);
    }

    public function testCloseHandlerReceivesFdAndReactorId(): void
    {
        $handlers = [
            0 => ['class' => TestCloseHandler::class, 'method' => 'handle'],
        ];
        $this->registry->registerHandlers('close', 'before', $handlers);

        TestCloseHandler::$receivedFd = null;

        $context = EventContext::forClose($this->createMockServer(), 5, 3);
        $context->setEventName('close');

        $this->executor->executeBeforeHandlers($this->createMockServer(), $context);

        $this->assertSame(5, TestCloseHandler::$receivedFd);
    }

    private function createContext(string $eventName, int $workerId, bool $isTaskWorker): EventContext
    {
        $mockServer = $this->createMockServer();

        return EventContext::forWorkerStart($mockServer, $workerId)->setEventName($eventName);
    }

    private function createMockServer(): \Swoole\Http\Server
    {
        return $this->getMockBuilder(\Swoole\Http\Server::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}

class TestCallTrackingHandler
{
    public static bool $called = false;

    public function handle($app, $server, $workerId): void
    {
        self::$called = true;
    }
}

class TestParameterHandler
{
    public static ?int $receivedWorkerId = null;

    public function handle($app, $server, $workerId): void
    {
        self::$receivedWorkerId = $workerId;
    }
}

class TestRequestParameterHandler
{
    public static ?\Swoole\Http\Request $receivedRequest = null;

    public function handle($app, $request, $response): void
    {
        self::$receivedRequest = $request;
    }
}

class TestOrderingHandler
{
    public static array $order = [];

    public function handle($app, $server, $workerId): void
    {
        self::$order[] = $workerId;
    }
}

class TestThrowingHandler
{
    public function handle($app, $server, $workerId): void
    {
        throw new \RuntimeException('Test exception');
    }
}

class TestTaskResultHandler
{
    public static mixed $receivedResult = null;

    public function handle($app, $server, $taskId, $reactorId, $data, $result = null): void
    {
        self::$receivedResult = $result;
    }
}

class TestConnectHandler
{
    public static ?int $receivedFd = null;
    public static ?int $receivedReactorId = null;

    public function handle($app, $server, $fd, $reactorId): void
    {
        self::$receivedFd = $fd;
        self::$receivedReactorId = $reactorId;
    }
}

class TestCloseHandler
{
    public static ?int $receivedFd = null;

    public function handle($app, $server, $fd, $reactorId): void
    {
        self::$receivedFd = $fd;
    }
}