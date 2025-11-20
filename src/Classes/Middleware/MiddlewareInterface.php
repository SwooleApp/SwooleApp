<?php

namespace Sidalex\SwooleApp\Classes\Middleware;

use Sidalex\SwooleApp\Application;

interface MiddlewareInterface
{
    public function process(
        \Swoole\Http\Request  $request,
        \Swoole\Http\Response $response,
        Application           $application,
        callable              $next
    ): \Swoole\Http\Response;
}