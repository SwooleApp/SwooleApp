<?php

namespace Sidalex\SwooleApp\Classes\Middleware;

use Sidalex\SwooleApp\Application;

abstract class AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }
    abstract public function process(
        \Swoole\Http\Request  $request,
        \Swoole\Http\Response $response,
        Application           $application,
        callable              $next
    ): \Swoole\Http\Response;
}