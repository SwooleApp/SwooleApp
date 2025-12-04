<?php

namespace Sidalex\SwooleApp\Classes\Middleware;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class GlobalMiddleware
{
    /**
     * @param string $middlewareClass
     * @param array<mixed> $options
     */
    public function __construct(
        public string $middlewareClass,
        public array  $options = []
    )
    {
    }
}