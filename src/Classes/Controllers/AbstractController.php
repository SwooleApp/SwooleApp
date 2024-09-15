<?php

namespace Sidalex\SwooleApp\Classes\Controllers;

use Sidalex\SwooleApp\Application;
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

}