<?php

namespace Sidalex\SwooleApp\Classes\Middleware;

interface ConfigurableMiddlewareInterface extends MiddlewareInterface
{
    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options = []);
}