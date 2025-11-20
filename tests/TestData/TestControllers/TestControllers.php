<?php
namespace tests\TestData\TestControllers;

use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Swoole\Http\Response;

#[Route(uri: '/api/v100500/test1', method: 'POST')]
class TestController extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/api/v2/{test_name}/v5', method: 'POST')]
class TestController2 extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: 'nonslash/api/v2/{test_name}/v5', method: 'POST')]
class TestNotValidRoutController extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

// Контроллеры для тестов middleware
#[Route(uri: '/test1', method: 'GET')]
class TestControllerWithoutMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test2', method: 'GET')]
#[Middleware(middlewareClass: \tests\TestData\TestControllers\TestMiddleware1::class)]
class TestControllerWithSingleMiddleware extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test3', method: 'GET')]
#[Middleware(middlewareClass: \tests\TestData\TestControllers\TestMiddleware1::class, options: ['option1' => 'value1'])]
#[Middleware(middlewareClass: \tests\TestData\TestControllers\TestMiddleware2::class, options: ['option2' => 'value2'])]
class TestControllerWithMultipleMiddlewares extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}

#[Route(uri: '/test4', method: 'GET')]
class TestControllerWithMiddlewaresInjection extends AbstractController
{
    public function execute(): Response
    {
        return $this->response;
    }
}