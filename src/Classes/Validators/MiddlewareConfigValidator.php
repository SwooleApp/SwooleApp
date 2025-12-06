<?php
namespace Sidalex\SwooleApp\Classes\Validators;

use Sidalex\SwooleApp\Classes\Middleware\MiddlewareInterface;
use Sidalex\SwooleApp\Classes\Utils\Utilities;

class MiddlewareConfigValidator implements ConfigValidatorInterface
{
    public function validate(\stdClass $config): void
    {
        if (isset($config->globalMiddlewares) && is_array($config->globalMiddlewares)) {
            $this->validateMiddlewareList($config->globalMiddlewares, 'globalMiddlewares');
        }

        if (isset($config->controllers) && is_array($config->controllers)) {
            // Валидация контроллеров может быть добавлена здесь при необходимости
        }
    }

    /**
     * @param array<int, array{class: string, options: array<mixed>}> $middlewares
     * @param string $configKey
     */
    private function validateMiddlewareList(array $middlewares, string $configKey): void
    {
        foreach ($middlewares as $index => $middlewareConfig) {
            $className = $this->extractMiddlewareClassName($middlewareConfig, $configKey, $index);

            if ($className !== null) {
                $this->validateMiddlewareClass($className, $configKey, $index);
            }
        }
    }

    /**
     * @param array<mixed>|string $middlewareConfig
     * @param string $configKey
     * @param int $index
     * @return string
     */
    private function extractMiddlewareClassName(mixed $middlewareConfig, string $configKey, int $index): string
    {
        if (is_string($middlewareConfig)) {
            return $middlewareConfig;
        }

        if (is_array($middlewareConfig) && isset($middlewareConfig['class']) && is_string($middlewareConfig['class'])) {
            return $middlewareConfig['class'];
        }

        throw new \InvalidArgumentException(
            "Invalid middleware configuration in {$configKey} at index {$index}. " .
            "Must be a string class name or array with 'class' key."
        );
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