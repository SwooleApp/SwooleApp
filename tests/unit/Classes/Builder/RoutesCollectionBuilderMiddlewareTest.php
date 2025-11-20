<?php
namespace tests\Classes\Builder;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder
 */
class RoutesCollectionBuilderMiddlewareTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     */
    public function testBuildRoutesCollectionWithControllerWithoutMiddlewares()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $builder = $this->injectClassList($builder, [TestControllerWithoutMiddlewares::class]);

        $routes = $builder->buildRoutesCollection();

        $this->assertCount(1, $routes);
        $this->assertArrayHasKey('middlewares', $routes[0]);
        $this->assertIsArray($routes[0]['middlewares']);
        $this->assertEmpty($routes[0]['middlewares']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     */
    public function testBuildRoutesCollectionWithControllerWithSingleMiddleware()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $builder = $this->injectClassList($builder, [TestControllerWithSingleMiddleware::class]);

        $routes = $builder->buildRoutesCollection();

        $this->assertCount(1, $routes);
        $this->assertCount(1, $routes[0]['middlewares']);
        $this->assertEquals(TestMiddleware1::class, $routes[0]['middlewares'][0]['class']);
        $this->assertEquals([], $routes[0]['middlewares'][0]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     */
    public function testBuildRoutesCollectionWithControllerWithMultipleMiddlewares()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $builder = $this->injectClassList($builder, [TestControllerWithMultipleMiddlewares::class]);

        $routes = $builder->buildRoutesCollection();

        $this->assertCount(1, $routes);
        $this->assertCount(2, $routes[0]['middlewares']);

        $this->assertEquals(TestMiddleware1::class, $routes[0]['middlewares'][0]['class']);
        $this->assertEquals(['option1' => 'value1'], $routes[0]['middlewares'][0]['options']);

        $this->assertEquals(TestMiddleware2::class, $routes[0]['middlewares'][1]['class']);
        $this->assertEquals(['option2' => 'value2'], $routes[0]['middlewares'][1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     */
    public function testBuildRoutesCollectionWithMultipleControllersWithMiddlewares()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $builder = $this->injectClassList($builder, [
            TestControllerWithSingleMiddleware::class,
            TestControllerWithMultipleMiddlewares::class
        ]);

        $routes = $builder->buildRoutesCollection();

        $this->assertCount(2, $routes);

        // Первый контроллер - один middleware
        $this->assertCount(1, $routes[0]['middlewares']);
        $this->assertEquals(TestMiddleware1::class, $routes[0]['middlewares'][0]['class']);

        // Второй контроллер - два middleware
        $this->assertCount(2, $routes[1]['middlewares']);
        $this->assertEquals(TestMiddleware1::class, $routes[1]['middlewares'][0]['class']);
        $this->assertEquals(TestMiddleware2::class, $routes[1]['middlewares'][1]['class']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getController
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::injectMiddlewares
     */
    public function testGetControllerWithMiddlewaresInjection()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $request = $this->createMock(Request::class);
        $request->server['request_uri'] = '/test'; // Добавляем request_uri
        $response = $this->createMock(Response::class);

        $routeCollectionItem = [
            'ControllerClass' => TestControllerWithMiddlewaresInjection::class,
            'middlewares' => [
                [
                    'class' => TestMiddleware1::class,
                    'options' => ['injected' => 'value']
                ]
            ],
            'parameters_fromURI' => []
        ];

        $controller = $builder->getController($routeCollectionItem, $request, $response);

        $this->assertInstanceOf(AbstractController::class, $controller);
        $this->assertInstanceOf(TestControllerWithMiddlewaresInjection::class, $controller);

        $middlewares = $controller->getMiddlewares();
        $this->assertCount(1, $middlewares);
        $this->assertEquals(TestMiddleware1::class, $middlewares[0]['class']);
        $this->assertEquals(['injected' => 'value'], $middlewares[0]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getController
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::injectMiddlewares
     */
    public function testGetControllerWithoutMiddlewares()
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $request = $this->createMock(Request::class);
        $request->server['request_uri'] = '/test'; // Добавляем request_uri
        $response = $this->createMock(Response::class);

        $routeCollectionItem = [
            'ControllerClass' => TestControllerWithoutMiddlewares::class,
            'middlewares' => [],
            'parameters_fromURI' => []
        ];

        $controller = $builder->getController($routeCollectionItem, $request, $response);

        $this->assertInstanceOf(AbstractController::class, $controller);
        $this->assertInstanceOf(TestControllerWithoutMiddlewares::class, $controller);

        $middlewares = $controller->getMiddlewares();
        $this->assertEmpty($middlewares);
    }

    private function getConfigWrapperMock()
    {
        $std = new \stdClass();
        $std->controllers = [];
        return new ConfigWrapper($std);
    }

    private function injectClassList(RoutesCollectionBuilder $builder, array $classList): RoutesCollectionBuilder
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('classList');
        $property->setAccessible(true);
        $property->setValue($builder, $classList);
        return $builder;
    }
}

/**
 * Test Controller Classes with Route attributes
 */
#[Route(uri: '/test1', method: 'GET')]
class TestControllerWithoutMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test2', method: 'GET')]
#[Middleware(TestMiddleware1::class)]
class TestControllerWithSingleMiddleware extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test3', method: 'GET')]
#[Middleware(TestMiddleware1::class, ['option1' => 'value1'])]
#[Middleware(TestMiddleware2::class, ['option2' => 'value2'])]
class TestControllerWithMultipleMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test4', method: 'GET')]
class TestControllerWithMiddlewaresInjection extends AbstractController
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
    public function process(Request $request, Response $response, \Sidalex\SwooleApp\Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestMiddleware2 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, \Sidalex\SwooleApp\Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}