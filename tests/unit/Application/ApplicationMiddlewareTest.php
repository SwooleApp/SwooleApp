<?php
namespace tests\Application;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

/**
 * @covers \Sidalex\SwooleApp\Application
 */
class ApplicationMiddlewareTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::initGlobalMiddlewares
     * @covers \Sidalex\SwooleApp\Application::getGlobalMiddlewares
     */
    public function testApplicationWithoutGlobalMiddlewares()
    {
        $config = new \stdClass();
        $config->controllers = [];

        $app = new Application($config);

        $globalMiddlewares = $app->getGlobalMiddlewares();
        $this->assertIsArray($globalMiddlewares);
        $this->assertEmpty($globalMiddlewares);
    }

    /**
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::initGlobalMiddlewares
     * @covers \Sidalex\SwooleApp\Application::getGlobalMiddlewares
     */
    public function testApplicationWithStringGlobalMiddlewares()
    {
        $config = new \stdClass();
        $config->controllers = [];
        $config->globalMiddlewares = [
            TestGlobalMiddleware1::class,
            TestGlobalMiddleware2::class
        ];

        $app = new Application($config);

        $globalMiddlewares = $app->getGlobalMiddlewares();
        $this->assertCount(2, $globalMiddlewares);

        $this->assertEquals(TestGlobalMiddleware1::class, $globalMiddlewares[0]['class']);
        $this->assertEquals([], $globalMiddlewares[0]['options']);

        $this->assertEquals(TestGlobalMiddleware2::class, $globalMiddlewares[1]['class']);
        $this->assertEquals([], $globalMiddlewares[1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::initGlobalMiddlewares
     * @covers \Sidalex\SwooleApp\Application::getGlobalMiddlewares
     */
    public function testApplicationWithArrayGlobalMiddlewares()
    {
        $config = new \stdClass();
        $config->controllers = [];
        $config->globalMiddlewares = [
            [
                'class' => TestGlobalMiddleware1::class,
                'options' => ['key1' => 'value1']
            ],
            [
                'class' => TestGlobalMiddleware2::class,
                'options' => ['key2' => 'value2']
            ]
        ];

        $app = new Application($config);

        $globalMiddlewares = $app->getGlobalMiddlewares();
        $this->assertCount(2, $globalMiddlewares);

        $this->assertEquals(TestGlobalMiddleware1::class, $globalMiddlewares[0]['class']);
        $this->assertEquals(['key1' => 'value1'], $globalMiddlewares[0]['options']);

        $this->assertEquals(TestGlobalMiddleware2::class, $globalMiddlewares[1]['class']);
        $this->assertEquals(['key2' => 'value2'], $globalMiddlewares[1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::initGlobalMiddlewares
     * @covers \Sidalex\SwooleApp\Application::getGlobalMiddlewares
     */
    public function testApplicationWithMixedGlobalMiddlewares()
    {
        $config = new \stdClass();
        $config->controllers = [];
        $config->globalMiddlewares = [
            TestGlobalMiddleware1::class,
            [
                'class' => TestGlobalMiddleware2::class,
                'options' => ['key' => 'value']
            ]
        ];

        $app = new Application($config);

        $globalMiddlewares = $app->getGlobalMiddlewares();
        $this->assertCount(2, $globalMiddlewares);

        $this->assertEquals(TestGlobalMiddleware1::class, $globalMiddlewares[0]['class']);
        $this->assertEquals([], $globalMiddlewares[0]['options']);

        $this->assertEquals(TestGlobalMiddleware2::class, $globalMiddlewares[1]['class']);
        $this->assertEquals(['key' => 'value'], $globalMiddlewares[1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Application::__construct
     * @covers \Sidalex\SwooleApp\Application::initGlobalMiddlewares
     */
    public function testApplicationWithMiddlewareConfigValidator()
    {
        $config = new \stdClass();
        $config->controllers = [];
        $config->globalMiddlewares = [
            TestGlobalMiddleware1::class
        ];

        $validators = [TestMiddlewareConfigValidator::class];

        $app = new Application($config, $validators);

        // Если валидация прошла успешно, приложение создалось без исключений
        $this->assertInstanceOf(Application::class, $app);

        $globalMiddlewares = $app->getGlobalMiddlewares();
        $this->assertCount(1, $globalMiddlewares);
    }
}

/**
 * Test Global Middleware Classes
 */
class TestGlobalMiddleware1 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestGlobalMiddleware2 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

/**
 * Test Config Validator
 */
class TestMiddlewareConfigValidator implements ConfigValidatorInterface
{
    public function validate(\stdClass $config): void
    {
        // Простая валидация - проверяем наличие globalMiddlewares
        if (isset($config->globalMiddlewares) && !is_array($config->globalMiddlewares)) {
            throw new \InvalidArgumentException('globalMiddlewares must be an array');
        }
    }
}