<?php
namespace tests\Classes\Builder;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder
 * @covers \Sidalex\SwooleApp\Classes\Validators\ValidatorUriArr
 * @covers \Sidalex\SwooleApp\Classes\Controllers\Route
 */
class RoutesCollectionBuilderTest extends TestCase
{
    /**
     * Создает mock ConfigWrapper с настройками для тестов
     *
     * @return ConfigWrapper Mock объект конфигурации
     */
    protected function getConfigWrapperMock(): ConfigWrapper
    {
        $std = new \stdClass();
        $std->controllers = [];
        return new ConfigWrapper($std);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::generateItemRout
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::extractMiddlewares
     * Тест проверяет структуру возвращаемого массива маршрутов
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkContract(): void
    {
        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());

        // Используем прямой список классов для тестирования
        $controllerClasses = [
            'tests\TestData\TestControllers\TestController'
        ];

        $build = $routesCollectionBuilder->buildRoutesCollection($controllerClasses);

        $this->assertIsArray($build, 'buildRoutesCollection() method return not Array');
        $this->assertNotEmpty($build, 'buildRoutesCollection() returned empty array');

        $this->assertIsArray($build[0]['route_pattern_list'], "contract buildRoutesCollection validation route_pattern_list is not array");
        $this->assertIsArray($build[0]['parameters_fromURI'], "contract buildRoutesCollection validation parameters_fromURI is not array");
        $this->assertIsString($build[0]['method'], "contract buildRoutesCollection validation method is not string");
        $this->assertIsString($build[0]['ControllerClass'], "contract buildRoutesCollection validation ControllerClass is not string");
        $this->assertIsArray($build[0]['middlewares'], "contract buildRoutesCollection validation middlewares is not array");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::generateItemRout
     * Тест проверяет корректность формирования route_pattern_list
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_pattern_list(): void
    {
        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());

        $controllerClasses = [
            'tests\TestData\TestControllers\TestController'
        ];

        $build = $routesCollectionBuilder->buildRoutesCollection($controllerClasses);

        $this->assertNotEmpty($build, 'No routes were built');
        $this->assertEquals('', $build[0]['route_pattern_list'][0], "contract route_pattern_list validation first element is not empty");
        $this->assertEquals('api', $build[0]['route_pattern_list'][1], "contract route_pattern_list validation second element is not api");
        $this->assertEquals('v100500', $build[0]['route_pattern_list'][2], "contract route_pattern_list validation three element is not v100500");
        $this->assertEquals('test1', $build[0]['route_pattern_list'][3], "contract route_pattern_list validation three element is not test1");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::generateItemRout
     * Тест проверяет генерацию нескольких маршрутов одновременно
     */
    public function testBuildRoutesCollection__build2Routes__GenerationRouteListFromApp__Success2RouteGeneration(): void
    {
        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());

        $controllerClasses = [
            'tests\TestData\TestControllers\TestController',
            'tests\TestData\TestControllers\TestController2'
        ];

        $build = $routesCollectionBuilder->buildRoutesCollection($controllerClasses);

        $this->assertCount(2, $build, 'Expected 2 routes for 2 controllers');

        // Проверяем структуру TestController
        $this->assertEquals(['', 'api', 'v100500', 'test1'], $build[0]['route_pattern_list']);
        $this->assertEquals([], $build[0]['parameters_fromURI']);
        $this->assertEquals('POST', $build[0]['method']);
        $this->assertEquals('tests\TestData\TestControllers\TestController', $build[0]['ControllerClass']);

        // Проверяем структуру TestController2
        $this->assertEquals(['', 'api', 'v2', '*', 'v5'], $build[1]['route_pattern_list']);
        $this->assertEquals([3 => 'test_name'], $build[1]['parameters_fromURI']);
        $this->assertEquals('POST', $build[1]['method']);
        $this->assertEquals('tests\TestData\TestControllers\TestController2', $build[1]['ControllerClass']);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::generateItemRout
     * @covers \Sidalex\SwooleApp\Classes\Validators\ValidatorUriArr::validate
     * Тест проверяет обработку некорректных маршрутов (без слеша в начале)
     */
    public function testBuildRoutesCollection__buildRouteNotStartingWithSlash__GenerationRouteListFromApp__AssertException(): void
    {
        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());

        $controllerClasses = [
            'tests\TestData\TestControllers\TestNotValidRoutController'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1);
        $this->expectExceptionMessage('uri in Route is not valid. uri must started from "/" symbol');

        $routesCollectionBuilder->buildRoutesCollection($controllerClasses);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::getRepositoryItems
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::generateItemRout
     * Тест проверяет корректность извлечения параметров из URI
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_parameters_fromURI__assertTestName(): void
    {
        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());

        $controllerClasses = [
            'tests\TestData\TestControllers\TestController2'
        ];

        $build = $routesCollectionBuilder->buildRoutesCollection($controllerClasses);

        $this->assertNotEmpty($build, 'No routes were built');
        $this->assertEquals('test_name', $build[0]['parameters_fromURI'][3], "contract parameters_fromURI not set params");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::searchInRoute
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::findMatchingElement
     * Тест проверяет поиск маршрута по статическим данным
     */
    public function testSearchInRoute__searchInMockStaticRoute__SuccessSearchItemRoute(): void
    {
        $swooleRequestStab = $this->createStub(\Swoole\Http\Request::class);
        $swooleRequestStab->method('getMethod')->willReturn('GET');
        $swooleRequestStab->server['request_uri'] = '/api/v3/collectionsList/';

        $routesCollectionBuilder = new RoutesCollectionBuilder($this->getConfigWrapperMock());
        $routesCollection = include './tests/TestData/mocks/routesCollection.php';

        // Обновляем мок-данные, добавляя поле middlewares
        $routesCollection = array_map(function($route) {
            $route['middlewares'] = [];
            return $route;
        }, $routesCollection);

        $result = $routesCollectionBuilder->searchInRoute($swooleRequestStab, $routesCollection);

        $expectedResult = array(
            'route_pattern_list' => array(
                0 => '',
                1 => 'api',
                2 => 'v3',
                3 => 'collectionsList',
            ),
            'parameters_fromURI' => array(),
            'method' => 'GET',
            'ControllerClass' => 'TestController4',
            'middlewares' => [],
        );

        $this->assertEquals($expectedResult, $result, 'fixed roure /api/v3/collectionsList found not correct ');
    }
}