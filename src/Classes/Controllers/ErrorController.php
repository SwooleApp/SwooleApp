<?php

namespace Sidalex\SwooleApp\Classes\Controllers;

use Sidalex\SwooleApp\Classes\Builder\ErrorResponseBuilder;

class ErrorController implements ControllerInterface
{
    /**
     * @var array<mixed>|string[]
     */
    protected array $errors_message;
    protected \Swoole\Http\Response $response;
    protected \Swoole\Http\Request $request;
    protected \Sidalex\SwooleApp\Application $application;

    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->errors_message = $uri_params;
    }

    public function execute(): \Swoole\Http\Response
    {
        $errorBuilder = new ErrorResponseBuilder($this->response);
        return $errorBuilder->errorResponse($this->errors_message['message']);
    }

    public function setApplication(\Sidalex\SwooleApp\Application $application, \Swoole\Http\Server $server): void
    {
        $this->application = $application;
    }
}