<?php

namespace Sidalex\SwooleApp\Classes\Validators;

interface ConfigValidatorInterface
{
    /**
     * @param \stdClass $config
     * @throws \Exception
     */
    public function validate(\stdClass $config): void;
}