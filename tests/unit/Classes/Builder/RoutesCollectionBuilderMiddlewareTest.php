<?php
namespace tests\Classes\Builder;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder
 * @covers \Sidalex\SwooleApp\Classes\Middleware\Middleware
 */
class RoutesCollectionBuilderMiddlewareTest extends TestCase
{
    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     * Тест проверяет сборку маршрутов для контроллера без middleware
     */
    public function testBuildRoutesCollectionWithControllerWithoutMiddlewares(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $controllerClasses = [
            'tests\TestData\TestControllers\TestControllerWithoutMiddlewares'
        ];

        $routes = $builder->buildRoutesCollection($controllerClasses);

        $this->assertCount(1, $routes, 'No routes found for controller without middlewares');
        $this->assertArrayHasKey('middlewares', $routes[0]);
        $this->assertIsArray($routes[0]['middlewares']);
        $this->assertEmpty($routes[0]['middlewares']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     * Тест проверяет сборку маршрутов для контроллера с одним middleware
     */
    public function testBuildRoutesCollectionWithControllerWithSingleMiddleware(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $controllerClasses = [
            'tests\TestData\TestControllers\TestControllerWithSingleMiddleware'
        ];

        $routes = $builder->buildRoutesCollection($controllerClasses);

        $this->assertCount(1, $routes, 'No routes found for controller with single middleware');
        $this->assertCount(1, $routes[0]['middlewares']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware1', $routes[0]['middlewares'][0]['class']);
        $this->assertEquals([], $routes[0]['middlewares'][0]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     * Тест проверяет сборку маршрутов для контроллера с несколькими middleware
     */
    public function testBuildRoutesCollectionWithControllerWithMultipleMiddlewares(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $controllerClasses = [
            'tests\TestData\TestControllers\TestControllerWithMultipleMiddlewares'
        ];

        $routes = $builder->buildRoutesCollection($controllerClasses);

        $this->assertCount(1, $routes, 'No routes found for controller with multiple middlewares');
        $this->assertCount(2, $routes[0]['middlewares']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware1', $routes[0]['middlewares'][0]['class']);
        $this->assertEquals(['option1' => 'value1'], $routes[0]['middlewares'][0]['options']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware2', $routes[0]['middlewares'][1]['class']);
        $this->assertEquals(['option2' => 'value2'], $routes[0]['middlewares'][1]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     * Тест проверяет сборку маршрутов для нескольких контроллеров с middleware
     */
    public function testBuildRoutesCollectionWithMultipleControllersWithMiddlewares(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);

        $controllerClasses = [
            'tests\TestData\TestControllers\TestControllerWithSingleMiddleware',
            'tests\TestData\TestControllers\TestControllerWithMultipleMiddlewares'
        ];

        $routes = $builder->buildRoutesCollection($controllerClasses);

        $this->assertCount(2, $routes, 'Expected 2 routes for 2 controllers');

        // Первый контроллер - один middleware
        $this->assertCount(1, $routes[0]['middlewares']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware1', $routes[0]['middlewares'][0]['class']);

        // Второй контроллер - два middleware
        $this->assertCount(2, $routes[1]['middlewares']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware1', $routes[1]['middlewares'][0]['class']);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware2', $routes[1]['middlewares'][1]['class']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getController
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::injectMiddlewares
     * Тест проверяет инъекцию middleware в контроллер
     */
    public function testGetControllerWithMiddlewaresInjection(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);
        $request = $this->createMock(\Swoole\Http\Request::class);
        $request->server['request_uri'] = '/test';
        $response = $this->createMock(\Swoole\Http\Response::class);

        $routeCollectionItem = [
            'ControllerClass' => 'tests\TestData\TestControllers\TestControllerWithMiddlewaresInjection',
            'middlewares' => [
                [
                    'class' => 'tests\TestData\TestControllers\TestMiddleware1',
                    'options' => ['injected' => 'value']
                ]
            ],
            'parameters_fromURI' => []
        ];

        $controller = $builder->getController($routeCollectionItem, $request, $response);

        $this->assertInstanceOf(\Sidalex\SwooleApp\Classes\Controllers\AbstractController::class, $controller);
        $this->assertInstanceOf('tests\TestData\TestControllers\TestControllerWithMiddlewaresInjection', $controller);

        $middlewares = $controller->getMiddlewares();
        $this->assertCount(1, $middlewares);
        $this->assertEquals('tests\TestData\TestControllers\TestMiddleware1', $middlewares[0]['class']);
        $this->assertEquals(['injected' => 'value'], $middlewares[0]['options']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getController
     * Тест проверяет создание контроллера без middleware
     */
    public function testGetControllerWithoutMiddlewares(): void
    {
        $configWrapper = $this->getConfigWrapperMock();
        $builder = new RoutesCollectionBuilder($configWrapper);
        $request = $this->createMock(\Swoole\Http\Request::class);
        $request->server['request_uri'] = '/test';
        $response = $this->createMock(\Swoole\Http\Response::class);

        $routeCollectionItem = [
            'ControllerClass' => 'tests\TestData\TestControllers\TestControllerWithoutMiddlewares',
            'middlewares' => [],
            'parameters_fromURI' => []
        ];

        $controller = $builder->getController($routeCollectionItem, $request, $response);

        $this->assertInstanceOf(\Sidalex\SwooleApp\Classes\Controllers\AbstractController::class, $controller);
        $this->assertInstanceOf('tests\TestData\TestControllers\TestControllerWithoutMiddlewares', $controller);

        $middlewares = $controller->getMiddlewares();
        $this->assertEmpty($middlewares);
    }

    /**
     * Создает mock ConfigWrapper с настройками для тестов middleware
     *
     * @return ConfigWrapper Mock объект конфигурации
     */
    private function getConfigWrapperMock(): ConfigWrapper
    {
        $std = new \stdClass();
        $std->controllers = [];
        return new ConfigWrapper($std);
    }
}