<?php

namespace Sidalex\SwooleApp\Classes\Controllers;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Middleware\ConfigurableMiddlewareInterface;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Swoole\Http\Server;

class NotFoundController implements ControllerInterface
{
    // @phpstan-ignore-next-line
    private \Swoole\Http\Request $request;
    private \Swoole\Http\Response $responce;
    /**
     * @var array|string[]
     */
    // @phpstan-ignore-next-line
    private array $uri_params;
    private array $middlewares = [];

    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params = [])
    {
        $this->request = $request;
        $this->responce = $response;
        $this->uri_params = $uri_params;
    }

    public function execute(): \Swoole\Http\Response
    {
        $this->responce->setStatusCode(404);
        $this->responce->setHeader('Content-Type', 'application/json');
        $this->responce->end(json_encode(
            [
                'codeStatus' => '404',
                'text' => 'Page not found'
            ]
        ));
        //todo when creating logs , then add to log request and $uri_params from dev mode
        return $this->responce;
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

    public function setApplication(Application $application, Server $server): void
    {

    }
}