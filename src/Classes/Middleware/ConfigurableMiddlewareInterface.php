<?php

namespace Sidalex\SwooleApp\Classes\Middleware;

interface ConfigurableMiddlewareInterface extends MiddlewareInterface
{
    public function __construct(array $options = []);
}