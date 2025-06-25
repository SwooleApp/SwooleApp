<?php
namespace tests\Classes\Builder;

use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

class RoutesCollectionBuilderTest extends TestCase
{
    protected function getConfigWrapperMock()
    {
        $std = new \stdClass();
        $std->controllers = [];
        return new ConfigWrapper($std);
    }

    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     *
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkContract()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder();
        $build = $routesCollectionBuilder->buildRoutesCollection(
            [
                'TestController',
            ]
        );
        $this->assertIsArray($build,'buildRoutesCollection() method return not Array');
        $this->assertIsArray($build[0]['route_pattern_list'],"contract buildRoutesCollection validation route_pattern_list is not array");
        $this->assertIsArray($build[0]['parameters_fromURI'],"contract buildRoutesCollection validation parameters_fromURI is not array");
        $this->assertIsString($build[0]['method'],"contract buildRoutesCollection validation method is not string");
        $this->assertIsString($build[0]['ControllerClass'],"contract buildRoutesCollection validation method is not string");
    }
    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     *
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_pattern_list()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder(        );
        $build = $routesCollectionBuilder->buildRoutesCollection(
            [
                'TestController',
            ]
        );
        $this->assertEquals('', $build[0]['route_pattern_list'][0],"contract route_pattern_list validation first element is not empty");
        $this->assertEquals('api', $build[0]['route_pattern_list'][1],"contract route_pattern_list validation second element is not api");
        $this->assertEquals('v100500', $build[0]['route_pattern_list'][2],"contract route_pattern_list validation three element is not v100500");
        $this->assertEquals('test1', $build[0]['route_pattern_list'][3],"contract route_pattern_list validation three element is not test1");
    }

    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     *
     */
    public function testBuildRoutesCollection__build2Routes__GenerationRouteListFromApp__Success2RouteGeneration()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder();
        $build = $routesCollectionBuilder->buildRoutesCollection(
            [
                'TestController',
                'TestController2',
            ]
        );
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
            ),
        );
        $this->assertEquals($result, $build);
    }

    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     *
     */
    public function testBuildRoutesCollection__buildRouteNotStartingWithSlash__GenerationRouteListFromApp__AssertException()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder();
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1);
        $build = $routesCollectionBuilder->buildRoutesCollection(
            [
                'TestNotValidRoutController'
            ]
        );
    }

    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::buildRoutesCollection
     *
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_parameters_fromURI__assertTestName()
    {
        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder();
        $build = $routesCollectionBuilder->buildRoutesCollection(
            [
                'TestController2',
            ]
        );
        $this->assertEquals('test_name', $build[0]['parameters_fromURI'][3],"contract parameters_fromURI not set params");
    }

    /**
     *
     * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder::searchInRoute
     *
     */
    public function testSearchInRoute__searchInMockStaticRoute__SuccessSearchItemRoute()
    {
        $swooleRequestStab = $this->createStub(\Swoole\Http\Request::class);
        $swooleRequestStab
            ->method('getMethod')
            ->willReturn('GET');
        $swooleRequestStab->server['request_uri'] = '/api/v3/collectionsList/';

        $routesCollectionBuilder = $this->getInjectedEmptyConfigRoutesBuilder();
        $routesCollection = include'./tests/TestData/mocks/routesCollection.php';
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
        );
        $this->assertEquals($expectedResult, $result, 'fixed roure /api/v3/collectionsList found not correct ');
    }

    private function getInjectedEmptyConfigRoutesBuilder(): RoutesCollectionBuilder
    {
        $configWrapper = $this->getConfigWrapperMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($configWrapper);


        return $routesCollectionBuilder;
    }
}