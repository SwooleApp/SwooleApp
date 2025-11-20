<?php

namespace Sidalex\SwooleApp\Classes\Controllers;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Middleware\ConfigurableMiddlewareInterface;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Swoole\Http\Server;

abstract class AbstractController implements ControllerInterface
{
    protected \Swoole\Http\Request $request;
    protected \Swoole\Http\Response $response;
    /**
     * @var string[] {key from Rute dynamic params, value from query uri} /api/{version}/customer ['version' => 'string from query uri']
     */
    protected array $uri_params;
    protected Application $application;
    protected Server $server;
    /**
     * @var MiddlewareInterface[]
     */
    protected array $middlewares = [];

    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->uri_params = $uri_params;
    }

    public abstract function execute(): \Swoole\Http\Response;

    public function setApplication(Application $application, Server $server): void
    {
        $this->application = $application;
        $this->server = $server;
    }
    /**
     * @return MiddlewareInterface[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Извлечение Middleware из атрибутов контроллера
     */
    protected function resolveMiddlewares(): void
    {
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            $attributeInstance = $attribute->newInstance();

            if ($attributeInstance instanceof Middleware) {
                $this->middlewares[] = [
                    'class' => $attributeInstance->middlewareClass,
                    'options' => $attributeInstance->options
                ];
            }
        }
    }

    public function executeWithMiddlewares(): \Swoole\Http\Response
    {
        $middlewares = $this->getMiddlewares();

        if (empty($middlewares)) {
            return $this->execute();
        }

        $runner = function ($request, $response) use (&$runner, &$middlewares) {
            if (empty($middlewares)) {
                return $this->execute();
            }

            $middlewareConfig = array_shift($middlewares);
            $middleware = $this->createMiddleware($middlewareConfig);

            return $middleware->process(
                $request,
                $response,
                $this->application,
                function ($req, $resp) use ($runner) {
                    return $runner($req, $resp);
                }
            );
        };

        return $runner($this->request, $this->response);
    }

    /**
     * Фабрика для создания объектов Middleware
     */
    protected function createMiddleware(array $config): MiddlewareInterface
    {
        $className = $config['class'];
        $options = $config['options'] ?? [];

        if (!Utilities::classImplementInterface($className, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException("Middleware class {$className} must implement MiddlewareInterface");
        }

        // Для Middleware с поддержкой конфигурации
        if (Utilities::classImplementInterface($className, ConfigurableMiddlewareInterface::class)) {
            return new $className($options);
        }

        return new $className();
    }

}