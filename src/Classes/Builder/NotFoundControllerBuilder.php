<?php

namespace Sidalex\SwooleApp\Classes\Builder;

use Sidalex\SwooleApp\Classes\Controllers\ControllerInterface;
use Sidalex\SwooleApp\Classes\Controllers\NotFoundController;
use Sidalex\SwooleApp\Classes\Utils\Utilities;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;

class NotFoundControllerBuilder
{
    private ConfigWrapper $config;
    private \Swoole\Http\Request $request;
    private \Swoole\Http\Response $response;

    /**
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @param ConfigWrapper $config
     */
    public function __construct(\Swoole\Http\Request $request, \Swoole\Http\Response $response, ConfigWrapper $config)
    {
        $this->config = $config;
        $this->request = $request;
        $this->response = $response;
    }

    public function build(): ControllerInterface
    {
        if (!empty($this->config->getConfigFromKey('notFoundController'))) {
            $className = $this->config->getConfigFromKey('notFoundController');
            if (Utilities::classImplementInterface($className, 'Sidalex\SwooleApp\Classes\Controllers\ControllerInterface')) {
                // @phpstan-ignore-next-line
                return new $className($this->request, $this->response);
            }
        }
        return new NotFoundController($this->request, $this->response);
    }

}