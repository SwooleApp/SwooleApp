<?php

namespace Sidalex\SwooleApp\Classes\Initiation;

interface StateContainerInitiationInterface
{
    public function init(\Sidalex\SwooleApp\Application $param);

    public function getKey(): string;

    public function getResultInitiation(): mixed;
}