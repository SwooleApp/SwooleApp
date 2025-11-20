<?php

namespace Sidalex\SwooleApp\Classes\Builder;

use HaydenPierce\ClassFinder\ClassFinder;
use Sidalex\SwooleApp\Classes\Controllers\ControllerInterface;
use Sidalex\SwooleApp\Classes\Controllers\ErrorController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Validators\ValidatorUriArr;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

class RoutesCollectionBuilder
{
    /**
     * @var array<int,string>
     */
    protected array $classList;
    protected ConfigWrapper $config;
    protected ValidatorUriArr $validatorUriArr;

    /**
     * @throws \Exception
     */
    public function __construct(ConfigWrapper $config)
    {
        $this->config = $config;
        $this->validatorUriArr = new ValidatorUriArr();
    }

    /**
     * @param string[]|null $controllerClassesList
     * @return array<int,array<mixed>>
     * example [
     *      [
     * 'route_pattern_list' => ['','api','*','get_resume'], // /api/{all_string_write_in_parameters_fromURI}/get_resume
     * 'parameters_fromURI' => [2 =>'v1'],
     * 'method' => 'POST',
     * 'ControllerClass' => '{class_nameSpace}',
     * 'middlewares' => []
     *      ]
     * ]
     * @throws \Exception
     * @throws \ReflectionException
     */
    public function buildRoutesCollection(?array $controllerClassesList=null): array
    {
        if(is_null($controllerClassesList)){
            $controllerClassesList = $this->getControllerClasses($this->config);
        }
        return $this->getRepositoryItems($controllerClassesList);
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
            $routeAttribute = $this->getRouteAttribute($class);
            if ($routeAttribute !== null) {
                $repositoryItem = $this->generateItemRout($routeAttribute, $class);
                $repository[] = $repositoryItem;
            }
        }
        return $repository;
    }

    /**
     * @return \ReflectionAttribute<Route>|null
     * @throws \ReflectionException
     */
    private function getRouteAttribute(string $class): ?\ReflectionAttribute
    {
        $attributes = $this->getAttributeReflection($class);

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Route::class) {
                /** @var \ReflectionAttribute<Route> $attribute */
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @param \ReflectionAttribute<object> $attributes
     * @param string $class
     * @return array<string,mixed>
     * example      [
     * 'route_pattern_list' => ['','api','*','get_resume'], // /api/{all_string_write_in_parameters_fromURI}/get_resume
     * 'parameters_fromURI' => [2 =>'v1'],
     * 'method' => 'POST',
     * 'ControllerClass' => '{class_nameSpace}',
     * 'middlewares' => []
     *      ]
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
        $repositoryItem['middlewares'] = $this->extractMiddlewares($class);

        return $repositoryItem;
    }

    /**
     * @param string $class
     * @return array<int, array<string, array<string,mixed>>>
     * @throws \ReflectionException
     */
    private function extractMiddlewares(string $class): array
    {
        $middlewares = [];
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();

            if ($attributeInstance instanceof Middleware) {
                $middlewares[] = [
                    'class' => $attributeInstance->middlewareClass,
                    'options' => $attributeInstance->options
                ];
            }
        }

        return $middlewares;
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

    public function getController(mixed $itemRouteCollection, \Swoole\Http\Request $request, \Swoole\Http\Response $response): ControllerInterface
    {
        $className = $itemRouteCollection['ControllerClass'];
        $uri = explode("/", $request->server['request_uri']);
        $UriParamsInjections = [];
        foreach ($itemRouteCollection['parameters_fromURI'] as $keyInUri => $keyInParamsName) {
            $UriParamsInjections[$keyInParamsName] = $uri[$keyInUri];
        }

        if (Utilities::classImplementInterface($className, ControllerInterface::class)) {
            /** @var ControllerInterface $controller */
            $controller = new $className($request, $response, $UriParamsInjections);

            if (isset($itemRouteCollection['middlewares'])) {
                $this->injectMiddlewares($controller, $itemRouteCollection['middlewares']);
            }

            return $controller;
        } else {
            return new ErrorController($request, $response);
        }
    }

    /**
     * Инъекция Middleware в контроллер
     */
    private function injectMiddlewares(ControllerInterface $controller, array $middlewaresConfig): void
    {
        if ($controller instanceof \Sidalex\SwooleApp\Classes\Controllers\AbstractController) {
            // Используем рефлексию для установки middlewares
            $reflection = new \ReflectionClass($controller);
            $property = $reflection->getProperty('middlewares');
            $property->setAccessible(true);
            $property->setValue($controller, $middlewaresConfig);
        }
    }
}