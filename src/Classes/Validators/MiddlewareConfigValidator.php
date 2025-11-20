<?php

namespace Sidalex\SwooleApp\Classes\Validators;

use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Classes\Utils\Utilities;

class MiddlewareConfigValidator implements ConfigValidatorInterface
{
    public function validate(\stdClass $config): void
    {
        // Валидация глобальных Middleware
        if (isset($config->globalMiddlewares) && is_array($config->globalMiddlewares)) {
            $this->validateMiddlewareList($config->globalMiddlewares, 'globalMiddlewares');
        }

        // Валидация Middleware в контроллерах (будет выполнена при построении маршрутов)
        if (isset($config->controllers) && is_array($config->controllers)) {
            // Контроллеры валидируются при построении маршрутов
        }
    }


    private function validateMiddlewareList(array $middlewares, string $configKey): void
    {
        foreach ($middlewares as $index => $middlewareConfig) {
            if (is_string($middlewareConfig)) {
                $className = $middlewareConfig;
                $options = [];
            } elseif (is_array($middlewareConfig) && isset($middlewareConfig['class'])) {
                $className = $middlewareConfig['class'];
                $options = $middlewareConfig['options'] ?? [];
            } else {
                throw new \InvalidArgumentException(
                    "Invalid middleware configuration in {$configKey} at index {$index}. " .
                    "Must be string or array with 'class' key"
                );
            }

            $this->validateMiddlewareClass($className, $configKey, $index);
        }
    }


    private function validateMiddlewareClass(string $className, string $configKey, int $index): void
    {
        // Проверка существования класса
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                "Middleware class '{$className}' not found in {$configKey} at index {$index}"
            );
        }

        // Проверка реализации интерфейса
        if (!Utilities::classImplementInterface($className, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException(
                "Middleware class '{$className}' must implement MiddlewareInterface in {$configKey} at index {$index}"
            );
        }
    }
}