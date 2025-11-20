<?php
namespace tests\TestData\TestControllers;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;

class TestMiddleware1 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}

class TestMiddleware2 implements MiddlewareInterface
{
    public function process(Request $request, Response $response, Application $application, callable $next): Response
    {
        return $next($request, $response);
    }
}