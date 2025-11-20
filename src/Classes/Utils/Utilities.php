<?php

namespace Sidalex\SwooleApp\Classes\Utils;

class Utilities
{
    public static function classImplementInterface(string $className, string $interfaceName): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $interfaceCollection = class_implements($className);

        if (is_array($interfaceCollection) && in_array($interfaceName, $interfaceCollection)) {
            return true;
        } else {
            return false;
        }
    }

}