<?php

namespace Sidalex\SwooleApp\Classes\Builder;

use HaydenPierce\ClassFinder\ClassFinder;
use Sidalex\SwooleApp\Classes\Controllers\ControllerInterface;
use Sidalex\SwooleApp\Classes\Controllers\ErrorController;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Validators\ValidatorUriArr;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

class RoutesCollectionBuilder
{
    /**
     * @var array<int,string>
     */
    protected array $classList;
    protected ValidatorUriArr $validatorUriArr;

    /**
     * @throws \Exception
     */
    public function __construct(ConfigWrapper $config)
    {
        $this->classList = $this->getControllerClasses($config);
        $this->validatorUriArr = new ValidatorUriArr();
    }

    /**
     * @return array<int,array<mixed>>
     * example [
     *      [
     * 'route_pattern_list' => ['','api','*','get_resume'], // /api/{all_string_write_in_parameters_fromURI}/get_resume
     * 'parameters_fromURI' => [2 =>'v1'],
     * 'method' => 'POST',
     * 'ControllerClass' => '{class_nameSpace}',
     *      ]
     * ]
     * @throws \Exception
     * @throws \ReflectionException
     */
    public function buildRoutesCollection(): array
    {
        $repository = $this->getRepositoryItems($this->classList);

        return $repository;
    }

    /**
     * @param ConfigWrapper $config
     * @return array<int,string>
     * @throws \Exception
     */
    private function getControllerClasses(ConfigWrapper $config): array
    {
        $classList = [];
        foreach ($config->getConfigFromKey('controllers') as $controller) {
            ClassFinder::disablePSR4Vendors();
            $classes = ClassFinder::getClassesInNamespace($controller, ClassFinder::RECURSIVE_MODE);
            $classList = array_merge($classList, $classes);
        }

        return $classList;
    }

    /**
     * @param array<int,string> $classList
     * @return array<int,array<mixed>>
     * @throws \Exception
     */
    private function getRepositoryItems(array $classList): array
    {
        $repository = [];
        foreach ($classList as $class) {
            $attributes = $this->getAttributeReflection($class);
            if (isset($attributes[0])) {
                if ($attributes[0]->getName() == 'Sidalex\\SwooleApp\\Classes\\Controllers\\Route') {
                    $repositoryItem = $this->generateItemRout($attributes[0], $class);
                    $repository[] = $repositoryItem;
                }
            }
        }
        return $repository;
    }

    /**
     * @param \ReflectionAttribute<object> $attributes
     * @param string $class
     * @return array<string,mixed> example  [
     *                               'route_pattern_list' =>
     *                                       [
     *                                           0 => '',
     *                                           1 => 'api',
     *                                           2 => 'v2',
     *                                           3 => '*',
     *                                           4 => 'v5',
     *                                       ],
     *                               'parameters_fromURI' =>
     *                                   [
     *                                       3 => 'test_name',
     *                                   ],
     *                               'method' => 'POST',
     *                               'ControllerClass' => 'TestController2',
     *                               ]
     * @throws \Exception
     */
    protected function generateItemRout(\ReflectionAttribute $attributes, string $class): array
    {
        $repositoryItem = [];
        $parameters_fromURIItem = [];
        $url_arr = explode('/', $attributes->getArguments()['uri']);
        $url_arr = $this->validatorUriArr->validate($url_arr);
        foreach ($url_arr as $number => $value) {
            $itemUri = $value;
            if ((str_starts_with($itemUri, '{')) && (str_ends_with($itemUri, '}'))) {
                $itemUri = "*";
                $parameters_fromURIItem[$number] = str_replace(['{', '}'], '', $value);
            }
            $repositoryItem['route_pattern_list'][$number] = $itemUri;
        }
        $repositoryItem['parameters_fromURI'] = $parameters_fromURIItem;
        $repositoryItem['method'] = $attributes->getArguments()['method'];
        $repositoryItem['ControllerClass'] = $class;
        return $repositoryItem;
    }

    /**
     * @param \Swoole\Http\Request $request
     * @param array<mixed> $routesCollection
     * @return array<mixed>|null
     */
    public function searchInRoute(\Swoole\Http\Request $request, array $routesCollection): array|null
    {
        $uri = explode("/", $request->server['request_uri']);
        return $this->findMatchingElement($routesCollection, $uri, $request->getMethod());

    }

    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     * @param string $method
     * @return array<int,array<mixed>>|null
     */
    protected function findMatchingElement(array $array1, array $array2, string $method): array|null
    {
        foreach ($array1 as $element) {
            $routePatternList = $element['route_pattern_list'];
            if (strtolower($method) != strtolower($element["method"])) {
                continue;
            }
            $match = true;
            for ($i = 0; $i < count($routePatternList); $i++) {
                if ($routePatternList[$i] !== '*'
                    && $routePatternList[$i] !== $array2[$i]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $element;
            }
        }
        return null;
    }

    public function getController(mixed $itemRouteCollection, \Swoole\Http\Request $request, \Swoole\Http\Response $response): ControllerInterface
    {
        $className = $itemRouteCollection['ControllerClass'];
        $uri = explode("/", $request->server['request_uri']);
        $UriParamsInjections = [];
        foreach ($itemRouteCollection['parameters_fromURI'] as $keyInUri => $keyInParamsName) {
            $UriParamsInjections[$keyInParamsName] = $uri[$keyInUri];
        }
        if (Utilities::classImplementInterface($className, 'Sidalex\SwooleApp\Classes\Controllers\ControllerInterface')) {
            // @phpstan-ignore-next-line
            return new $className($request, $response, $UriParamsInjections);
        } else {
            return new ErrorController($request, $response);
        }
    }

    /**
     * @param string $class
     * @return \ReflectionAttribute<object>[]
     * @throws \ReflectionException
     */
    private function getAttributeReflection(string $class): array
    {
        // @phpstan-ignore-next-line
        $reflection = new \ReflectionClass($class);
        return $reflection->getAttributes();
    }
}