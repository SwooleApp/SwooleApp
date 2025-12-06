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

    private \Swoole\Http\Request $request;
    private \Swoole\Http\Response $response;
    /**
     * @var array|string[]
     */
    // @phpstan-ignore-next-line
    private array $uri_params;
    /**
     * @var array<int, array{class: string, options: array<mixed>}>
     */
    private array $middlewares = [];
    private Application $application;
    // @phpstan-ignore-next-line
    private Server $server;


    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->uri_params = $uri_params;
    }

    public function execute(): \Swoole\Http\Response
    {
        $this->response->setStatusCode(404);
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->end(json_encode(
            [
                'codeStatus' => '404',
                'text' => 'Page not found'
            ]
        ));
        //todo when creating logs , then add to log request and $uri_params from dev mode
        return $this->response;
    }

    /**
     * @return array<int, array{class: string, options: array<mixed>}>
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
     * @param array{class: string, options: array<mixed>} $config
     * @return MiddlewareInterface
     */
    protected function createMiddleware(array $config): MiddlewareInterface
    {
        $className = $config['class'];
        $options = $config['options'];

        if (!Utilities::classImplementInterface($className, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException("Middleware class {$className} must implement MiddlewareInterface");
        }

        // Для Middleware с поддержкой конфигурации
        if (Utilities::classImplementInterface($className, ConfigurableMiddlewareInterface::class)) {
            /**
             * @var ConfigurableMiddlewareInterface
             */
            return new $className($options);
        }
        /**
         * @var ConfigurableMiddlewareInterface
         */
        return new $className();
    }

    public function setApplication(Application $application, Server $server): void
    {
        $this->application = $application;
        $this->server = $server;
    }
}