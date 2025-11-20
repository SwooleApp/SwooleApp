<?php
namespace tests\Classes\Builder;

use Classes\Builder\ReflectionClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder
 */
class RoutesCollectionBuilderTest extends TestCase
{
    protected function getConfigWrapperMock()
    {
        $std = new \stdClass();
        $std->controllers = [];
        return new ConfigWrapper($std);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkContract()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(
            [
                'TestController',
            ]
        );
        $build = $routesCollectionBuilder->buildRoutesCollection();
        $this->assertIsArray($build,'buildRoutesCollection() method return not Array');
        $this->assertIsArray($build[0]['route_pattern_list'],"contract buildRoutesCollection validation route_pattern_list is not array");
        $this->assertIsArray($build[0]['parameters_fromURI'],"contract buildRoutesCollection validation parameters_fromURI is not array");
        $this->assertIsString($build[0]['method'],"contract buildRoutesCollection validation method is not string");
        $this->assertIsString($build[0]['ControllerClass'],"contract buildRoutesCollection validation method is not string");
        $this->assertIsArray($build[0]['middlewares'],"contract buildRoutesCollection validation middlewares is not array");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_pattern_list()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(
            [
                'TestController',
            ]
        );
        $build = $routesCollectionBuilder->buildRoutesCollection();
        $this->assertEquals('', $build[0]['route_pattern_list'][0],"contract route_pattern_list validation first element is not empty");
        $this->assertEquals('api', $build[0]['route_pattern_list'][1],"contract route_pattern_list validation second element is not api");
        $this->assertEquals('v100500', $build[0]['route_pattern_list'][2],"contract route_pattern_list validation three element is not v100500");
        $this->assertEquals('test1', $build[0]['route_pattern_list'][3],"contract route_pattern_list validation three element is not test1");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     */
    public function testBuildRoutesCollection__build2Routes__GenerationRouteListFromApp__Success2RouteGeneration()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(
            [
                'TestController',
                'TestController2',
            ]
        );
        $build = $routesCollectionBuilder->buildRoutesCollection();
        $result = array(
            0 => array(
                'route_pattern_list' => array(
                    0 => '',
                    1 => 'api',
                    2 => 'v100500',
                    3 => 'test1',
                ),
                'parameters_fromURI' => array(),
                'method' => 'POST',
                'ControllerClass' => 'TestController',
                'middlewares' => [],
            ),
            1 => array(
                'route_pattern_list' => array(
                    0 => '',
                    1 => 'api',
                    2 => 'v2',
                    3 => '*',
                    4 => 'v5',
                ),
                'parameters_fromURI' => array(
                    3 => 'test_name',
                ),
                'method' => 'POST',
                'ControllerClass' => 'TestController2',
                'middlewares' => [],
            ),
        );
        $this->assertEquals($build, $result);
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     */
    public function testBuildRoutesCollection__buildRouteNotStartingWithSlash__GenerationRouteListFromApp__AssertException()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(
            [
                'TestNotValidRoutController'
            ]
        );
        try {
            $build = $routesCollectionBuilder->buildRoutesCollection();
        } catch (\Exception $exception){
            $this->assertEquals(1, $exception->getCode(),"Error Validation URI");
        }
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_parameters_fromURI__assertTestName()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(
            [
                'TestController2',
            ]
        );
        $build = $routesCollectionBuilder->buildRoutesCollection();
        $this->assertEquals('test_name', $build[0]['parameters_fromURI'][3],"contract parameters_fromURI not set params");
    }

    /**
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::searchInRoute
     */
    public function testSearchInRoute__searchInMockStaticRoute__SuccessSearchItemRoute()
    {
        $swooleRequestStab = $this->createStub(\Swoole\Http\Request::class);
        $swooleRequestStab
            ->method('getMethod')
            ->willReturn('GET');
        $swooleRequestStab->server['request_uri'] = '/api/v3/collectionsList/';
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder([]);
        $routesCollection = include'./tests/TestData/mocks/routesCollection.php';

        // Обновляем мок-данные, добавляя поле middlewares
        $routesCollection = array_map(function($route) {
            $route['middlewares'] = [];
            return $route;
        }, $routesCollection);

        $result=$routesCollectionBuilder->searchInRoute($swooleRequestStab,$routesCollection);
        $expectedResult = array (
            'route_pattern_list' => array (
                0 => '',
                1 => 'api',
                2 => 'v3',
                3 => 'collectionsList',
            ),
            'parameters_fromURI' => array (
            ),
            'method' => 'GET',
            'ControllerClass' => 'TestController4',
            'middlewares' => [],
        );
        $this->assertEquals($result,$expectedResult,'fixed roure /api/v3/collectionsList found not correct ');
    }

    private function getInjectedEmptyConfigRoutesBuilder(array $classList = []): RoutesCollectionBuilder
    {
        $configWrapper = $this->getConfigWrapperMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($configWrapper);
        $reflectionClass = new \ReflectionClass($routesCollectionBuilder);
        $reflectionProperty = $reflectionClass->getProperty('classList');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($routesCollectionBuilder, $classList );
        return $routesCollectionBuilder;
    }
}