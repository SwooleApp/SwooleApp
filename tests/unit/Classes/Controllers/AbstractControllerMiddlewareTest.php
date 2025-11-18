<?php
namespace tests\Classes\Controllers;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Application;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController
 */
class AbstractControllerMiddlewareTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::getMiddlewares
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::resolveMiddlewares
     */
    public function testControllerWithoutMiddlewares()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $controller = new TestControllerWithoutMiddlewares($request, $response);

        $this->assertEmpty($controller->getMiddlewares());
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::getMiddlewares
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::resolveMiddlewares
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::createMiddleware
     */
    public function testControllerWithMiddlewares()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $controller = new TestControllerWithMiddlewares($request, $response);

        // Вызываем resolveMiddlewares через рефлексию
        $reflection = new \ReflectionClass($controller);
        $resolveMethod = $reflection->getMethod('resolveMiddlewares');
        $resolveMethod->setAccessible(true);
        $resolveMethod->invoke($controller);

        $middlewares = $controller->getMiddlewares();
        $this->assertCount(2, $middlewares);
        $this->assertEquals(TestMiddleware1::class, $middlewares[0]['class']);
        $this->assertEquals(['option1' => 'value1'], $middlewares[0]['options']);
        $this->assertEquals(TestMiddleware2::class, $middlewares[1]['class']);
        $this->assertEquals([], $middlewares[1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::executeWithMiddlewares
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::createMiddleware
     */
    public function testExecuteWithMiddlewares()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $controller = new TestControllerWithExecution($request, $response);
        $controller->setApplication($application, $server);

        $result = $controller->executeWithMiddlewares();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame($response, $result);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::executeWithMiddlewares
     */
    public function testExecuteWithMiddlewaresEmpty()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);
        $application = $this->createMock(Application::class);
        $server = $this->createMock(Server::class);

        $controller = new TestControllerWithoutMiddlewares($request, $response);
        $controller->setApplication($application, $server);

        $result = $controller->executeWithMiddlewares();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame($response, $result);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::createMiddleware
     */
    public function testCreateConfigurableMiddleware()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $controller = new TestControllerWithoutMiddlewares($request, $response);

        $middlewareConfig = [
            'class' => TestConfigurableMiddleware::class,
            'options' => ['test' => 'value']
        ];

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($controller);
        $createMethod = $reflection->getMethod('createMiddleware');
        $createMethod->setAccessible(true);

        $middleware = $createMethod->invoke($controller, $middlewareConfig);

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
        $this->assertInstanceOf(TestConfigurableMiddleware::class, $middleware);

        // Проверяем что опции установились
        $reflectionMiddleware = new \ReflectionClass($middleware);
        $optionsProperty = $reflectionMiddleware->getProperty('options');
        $optionsProperty->setAccessible(true);
        $this->assertEquals(['test' => 'value'], $optionsProperty->getValue($middleware));
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::createMiddleware
     */
    public function testCreateSimpleMiddleware()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $controller = new TestControllerWithoutMiddlewares($request, $response);

        $middlewareConfig = [
            'class' => TestSimpleMiddleware::class,
            'options' => []
        ];

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($controller);
        $createMethod = $reflection->getMethod('createMiddleware');
        $createMethod->setAccessible(true);

        $middleware = $createMethod->invoke($controller, $middlewareConfig);

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
        $this->assertInstanceOf(TestSimpleMiddleware::class, $middleware);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Controllers\AbstractController::createMiddleware
     */
    public function testCreateMiddlewareWithInvalidClass()
    {
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $controller = new TestControllerWithoutMiddlewares($request, $response);

        $middlewareConfig = [
            'class' => 'InvalidMiddlewareClass',
            'options' => []
        ];

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($controller);
        $createMethod = $reflection->getMethod('createMiddleware');
        $createMethod->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware class InvalidMiddlewareClass must implement MiddlewareInterface');

        $createMethod->invoke($controller, $middlewareConfig);
    }
}

/**
 * Test Controller Classes
 */
#[Middleware(TestMiddleware1::class, ['option1' => 'value1'])]
#[Middleware(TestMiddleware2::class)]
class TestControllerWithMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

class TestControllerWithoutMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

class TestControllerWithExecution extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

/**
 * Test Middleware Classes
 */
class TestMiddleware1 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestMiddleware2 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestConfigurableMiddleware implements MiddlewareInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestSimpleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}