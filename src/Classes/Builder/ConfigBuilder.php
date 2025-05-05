<?php
namespace Sidalex\SwooleApp\Classes\Builder;

use Sidalex\SwooleApp\Classes\Constants\ApplicationConstants;
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;

class ConfigBuilder {
    protected \stdClass $config;
    protected array $errors = [];
    protected array $envVariables;
    protected string $envFilePath;

    public function __construct(\stdClass $baseConfig = null, array $envVariables = null, ?string $envFilePath = null) {
        $this->envFilePath = $envFilePath ?? getcwd() . '/.env';
        $this->config = $baseConfig ?? new \stdClass();
        $this->envVariables = $envVariables ?? $_ENV;
        $this->loadEnvConfig();
    }

    public function getConfig(): \stdClass {
        return $this->config;
    }

    public function validate(array $validators): bool {
        foreach ($validators as $validatorClass) {
            try {
                if (!class_exists($validatorClass)) {
                    throw new \InvalidArgumentException("Validator class {$validatorClass} not found");
                }
                $validator = new $validatorClass();
                if (!$validator instanceof ConfigValidatorInterface) {
                    throw new \InvalidArgumentException("Validator must implement ConfigValidatorInterface");
                }
                $validator->validate($this->config);
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    protected function loadEnvConfig(): void {
        $this->loadDotEnv();
        foreach ($this->envVariables as $key => $value) {
            if (str_starts_with($key, ApplicationConstants::APP_ENV_PREFIX)) {
                $this->processConfigKey($key, $value);
            }
        }
    }

    protected function loadDotEnv(): void {
        if (!file_exists($this->envFilePath)) {
            return;
        }

        $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                if (str_starts_with($key, ApplicationConstants::APP_ENV_PREFIX)) {
                    $this->envVariables[$key] = $this->parseValue($value);
                }
            }
        }
    }

    protected function processConfigKey(string $key, $value): void {
        $path = substr($key, strlen(ApplicationConstants::APP_ENV_PREFIX));
        $parts = explode('__', $path);
        $current = &$this->config;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $this->setFinalValue($current, $part, $value);
            } else {
                if (!isset($current->$part)) {
                    $current->$part = new \stdClass();
                }
                $current = &$current->$part;
            }
        }
    }

    protected function setFinalValue(&$current, string $part, $value): void {
        $parsedValue = $this->parseValue($value);

        if (is_numeric($part)) {
            if (!is_array($current)) {
                $current = [];
            }
            $current[$part] = $parsedValue;
        } else {
            if (!is_object($current)) {
                $current = new \stdClass();
            }
            $current->$part = $parsedValue;
        }
    }

    protected function parseValue($value) {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $lowerValue = strtolower($value);

        if ($lowerValue === 'true') return true;
        if ($lowerValue === 'false') return false;
        if ($lowerValue === 'null') return null;

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }
}