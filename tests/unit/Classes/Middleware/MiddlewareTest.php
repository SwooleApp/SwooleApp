<?php
namespace tests\Classes\Middleware;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Middleware\AbstractMiddleware;
use Sidalex\SwooleApp\Classes\Middleware\ConfigurableMiddlewareInterface;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Application;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * @covers \Sidalex\SwooleApp\Classes\Middleware\AbstractMiddleware
 * @covers \Sidalex\SwooleApp\Classes\Middleware\Middleware
 * @covers \Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface
 * @covers \Sidalex\SwooleApp\Classes\Middleware\ConfigurableMiddlewareInterface
 */
class MiddlewareTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\Middleware\Middleware::__construct
     */
    public function testMiddlewareAttributeCreation()
    {
        $middlewareClass = 'TestMiddleware';
        $options = ['key' => 'value'];

        $middlewareAttribute = new Middleware($middlewareClass, $options);

        $this->assertEquals($middlewareClass, $middlewareAttribute->middlewareClass);
        $this->assertEquals($options, $middlewareAttribute->options);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Middleware\Middleware::__construct
     */
    public function testMiddlewareAttributeCreationWithEmptyOptions()
    {
        $middlewareClass = 'TestMiddleware';

        $middlewareAttribute = new Middleware($middlewareClass);

        $this->assertEquals($middlewareClass, $middlewareAttribute->middlewareClass);
        $this->assertEquals([], $middlewareAttribute->options);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Middleware\AbstractMiddleware::__construct
     */
    public function testAbstractMiddlewareWithOptions()
    {
        $options = ['test' => 'value'];

        $middleware = $this->getMockForAbstractClass(
            AbstractMiddleware::class,
            [$options]
        );

        $reflection = new \ReflectionClass($middleware);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);

        $this->assertEquals($options, $optionsProperty->getValue($middleware));
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Middleware\AbstractMiddleware::__construct
     */
    public function testAbstractMiddlewareWithEmptyOptions()
    {
        $middleware = $this->getMockForAbstractClass(AbstractMiddleware::class);

        $reflection = new \ReflectionClass($middleware);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);

        $this->assertEquals([], $optionsProperty->getValue($middleware));
    }
}

/**
 * Test Middleware classes for testing
 */
class TestConfigurableMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        $response->header('X-Test-Configurable', 'true');
        $response->header('X-Test-Options', json_encode($this->options));
        return $next($request, $response);
    }
}

class TestSimpleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        $response->header('X-Test-Simple', 'true');
        return $next($request, $response);
    }
}