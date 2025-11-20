<?php
namespace Sidalex\SwooleApp\Classes\Controllers;

use Attribute;

#[Attribute]
class Route
{
    // @phpstan-ignore-next-line
    public string $uri;
    // @phpstan-ignore-next-line
    public string $method;

    /**
     * @param string $uri
     * @param string $method
     */
    public function __construct(string $uri, string $method)
    {
        $this->uri = $uri;
        $this->method = $method;
    }
}