<?php

namespace Sidalex\SwooleApp\Classes\Controllers;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

interface ControllerInterface
{
    /**
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @param array<string> $uri_params /v1/{module}/{entity} /v1/Accounts/123 ['module' => 'Accounts', 'entity' => '123']
     */
    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, array $uri_params = []);

    public function execute(): \Swoole\Http\Response;

    public function setApplication(Application $application, Server $server): void;
}
