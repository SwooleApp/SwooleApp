<?php
namespace tests\Classes\Builder;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

/**
 * @covers \Sidalex\SwooleApp\Classes\Builder\RoutesCollectionBuilder
 * @covers \Sidalex\SwooleApp\Classes\Validators\ValidatorUriArr
 */
class RoutesCollectionBuilderTest extends TestCase
{
    protected function getApplicationMock(): Application
    {
        $config = new \stdClass();
        $config->controllers = [];
        $configWrapper = new ConfigWrapper($config);
        $container = $this->createMock(Container::class);

        $app = $this->createMock(Application::class);
        $app->method('getConfig')->willReturn($configWrapper);
        $app->method('getDIContainer')->willReturn($container);

        return $app;
    }

    /**
     * Tests the basic structure of returned routes array
     * Verifies that buildRoutesCollection method returns array with correct structure
     * @test
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkContract()
    {
        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $build = $routesCollectionBuilder->buildRoutesCollection(['TestController']);

        $this->assertIsArray($build, 'buildRoutesCollection() method return not Array');
        $this->assertIsArray($build[0]['route_pattern_list'], "contract buildRoutesCollection validation route_pattern_list is not array");
        $this->assertIsArray($build[0]['parameters_fromURI'], "contract buildRoutesCollection validation parameters_fromURI is not array");
        $this->assertIsString($build[0]['method'], "contract buildRoutesCollection validation method is not string");
        $this->assertIsString($build[0]['ControllerClass'], "contract buildRoutesCollection validation method is not string");
    }

    /**
     * Tests correct formation of route_pattern_list from controller URI
     * Verifies URI splitting into separate components and their correct placement in array
     * @test
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_pattern_list()
    {
        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $build = $routesCollectionBuilder->buildRoutesCollection(['TestController']);

        $this->assertEquals('', $build[0]['route_pattern_list'][0], "contract route_pattern_list validation first element is not empty");
        $this->assertEquals('api', $build[0]['route_pattern_list'][1], "contract route_pattern_list validation second element is not api");
        $this->assertEquals('v100500', $build[0]['route_pattern_list'][2], "contract route_pattern_list validation three element is not v100500");
        $this->assertEquals('test1', $build[0]['route_pattern_list'][3], "contract route_pattern_list validation three element is not test1");
    }

    /**
     * Tests generation of multiple routes from multiple controllers
     * Verifies correct creation of routes for different URIs with parameters
     * @test
     */
    public function testBuildRoutesCollection__build2Routes__GenerationRouteListFromApp__Success2RouteGeneration()
    {
        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $build = $routesCollectionBuilder->buildRoutesCollection(['TestController', 'TestController2']);

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
     * Tests URI validation for routes not starting with slash
     * Expects exception when trying to create route with incorrect URI
     * @test
     */
    public function testBuildRoutesCollection__buildRouteNotStartingWithSlash__GenerationRouteListFromApp__AssertException()
    {
        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1);

        $routesCollectionBuilder->buildRoutesCollection(['TestNotValidRoutController']);
    }

    /**
     * Tests parameter extraction from URI with placeholders
     * Verifies correct identification of parameter names from URI template
     * @test
     */
    public function testBuildRoutesCollection__buildRoute_GenerationRouteListFromApp__checkRoute_parameters_fromURI__assertTestName()
    {
        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $build = $routesCollectionBuilder->buildRoutesCollection(['TestController2']);

        $this->assertEquals('test_name', $build[0]['parameters_fromURI'][3], "contract parameters_fromURI not set params");
    }

    /**
     * Tests route search in collection by request URI
     * Verifies correct matching of static URI with route in collection
     * @test
     */
    public function testSearchInRoute__searchInMockStaticRoute__SuccessSearchItemRoute()
    {
        $swooleRequestStab = $this->createStub(\Swoole\Http\Request::class);
        $swooleRequestStab->method('getMethod')->willReturn('GET');
        $swooleRequestStab->server['request_uri'] = '/api/v3/collectionsList/';

        $app = $this->getApplicationMock();
        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $routesCollection = include './tests/TestData/mocks/routesCollection.php';

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

    /**
     * Tests usage of DI container for controller creation
     * Verifies that controller is created through container with correct parameters
     * @test
     */
    public function testGetControllerUsesDIContainer()
    {
        $swooleRequest = $this->createStub(\Swoole\Http\Request::class);
        $swooleResponse = $this->createStub(\Swoole\Http\Response::class);
        $mockController = $this->createMock(\Sidalex\SwooleApp\Classes\Controllers\ControllerInterface::class);

        // Устанавливаем server['request_uri'] чтобы избежать null в explode()
        $swooleRequest->server = ['request_uri' => '/api/test'];

        $container = $this->createMock(Container::class);
        $container->expects($this->once())
            ->method('make')
            ->with(
                'TestController',
                [
                    'request' => $swooleRequest,
                    'response' => $swooleResponse,
                    'uri_params' => []
                ]
            )
            ->willReturn($mockController);

        $config = new \stdClass();
        $config->controllers = [];
        $configWrapper = new ConfigWrapper($config);

        $app = $this->createMock(Application::class);
        $app->method('getConfig')->willReturn($configWrapper);
        $app->method('getDIContainer')->willReturn($container);

        $routesCollectionBuilder = new RoutesCollectionBuilder($app);
        $itemRouteCollection = [
            'ControllerClass' => 'TestController',
            'parameters_fromURI' => []
        ];

        $controller = $routesCollectionBuilder->getController($itemRouteCollection, $swooleRequest, $swooleResponse);

        $this->assertSame($mockController, $controller);
    }
}
