<?php
namespace Sidalex\SwooleApp\Classes\Builder;

use Sidalex\SwooleApp\Classes\Constants\ApplicationConstants;
use Sidalex\SwooleApp\Classes\Dispatcher\DispatcherInterface;
use Sidalex\SwooleApp\Classes\Validators\ConfigValidatorInterface;

class ConfigBuilder {
    protected \stdClass $config;
    /**
     * @var string[]
     */
    protected array $errors = [];
    /**
     * @var mixed[]
     */
    protected array $envVariables;
    protected string $envFilePath;
    protected bool $dotEnvLoaded = false;

    /**
     * @param \stdClass|null $baseConfig
     * @param mixed[]|null $envVariables
     * @param string|null $envFilePath
     */
    public function __construct(?\stdClass $baseConfig = null, ?array $envVariables = null, ?string $envFilePath = null) {
        $this->envFilePath = $envFilePath ?? getcwd() . '/.env';
        $this->config = $baseConfig ?? new \stdClass();
        $this->envVariables = $envVariables ?? $_ENV;
        $this->loadEnvConfig();
    }

    public function getConfig(): \stdClass {
        return $this->config;
    }

    /**
     * @param string[] $validators
     * @return bool
     */
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

    /**
     * @return string[]
     */
    public function getErrors(): array {
        return $this->errors;
    }

    protected function loadEnvConfig(): void {
        $this->loadDotEnv();
    }

    protected function loadDotEnv(): void {
        if ($this->dotEnvLoaded) {
            return;
        }

        if (file_exists($this->envFilePath)) {
            $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        if (str_starts_with($key, ApplicationConstants::APP_ENV_PREFIX)) {
                            $this->envVariables[$key] = $value;
                        }
                    }
                }
            }
        }

        $this->dotEnvLoaded = true;

        foreach ($this->envVariables as $key => $value) {
            if (str_starts_with($key, ApplicationConstants::APP_ENV_PREFIX)) {
                $parsedValue = $this->parseValue($value);
                $this->processConfigKey($key, $parsedValue);
            }
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function processConfigKey(string $key, mixed $value): void {
        $path = substr($key, strlen(ApplicationConstants::APP_ENV_PREFIX));
        $parts = explode('__', $path);
        $current = &$this->config;
        $refs = [&$current];

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $this->setFinalValue($refs, $part, $value);
                return;
            }

            if (is_numeric($part)) {
                if (!is_array($current)) {
                    $current = [];
                }
                if (!isset($current[$part])) {
                    $current[$part] = new \stdClass();
                }
                $current = &$current[$part];
            } else {
                if (!is_object($current)) {
                    $current = new \stdClass();
                }
                if (!isset($current->$part)) {
                    $current->$part = new \stdClass();
                }
                $current = &$current->$part;
            }
            $refs[] = &$current;
        }
    }

    /**
     * @param array<int, mixed> $refs
     * @param string $part
     * @param mixed $value
     * @return void
     */
    protected function setFinalValue(array &$refs, string $part, mixed $value): void {
        $lastIndex = count($refs) - 1;
        $current = &$refs[$lastIndex];

        if (is_numeric($part)) {
            if (!is_array($current)) {
                $current = [];
            }
            $current[$part] = $value;
        } else {
            if (!is_object($current)) {
                $current = new \stdClass();
            }
            $current->$part = $value;
        }
    }

    /**
     * @param mixed $value
     * @return bool|float|int|mixed|string|null
     */
    protected function parseValue(mixed $value) {
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

    /**
     * @return array<string, mixed>
     */
    public function buildServerConfig(?DispatcherInterface $dispatcher = null): array {
        $config = [];

        $swooleConfig = $this->config->SWOOLE ?? null;
        if (is_array($swooleConfig)) {
            foreach ($swooleConfig as $key => $value) {
                $config[$key] = $value;
            }
        } elseif (is_object($swooleConfig)) {
            foreach (get_object_vars($swooleConfig) as $key => $value) {
                $config[$key] = $value;
            }
        }

        if (!isset($config['worker_num'])) {
            $config['worker_num'] = \swoole_cpu_num();
        }

        if (!isset($config['task_worker_num'])) {
            $config['task_worker_num'] = \swoole_cpu_num() * 10;
        }

        $dispatchFunc = $this->buildDefaultDispatchFunction();
        if ($dispatchFunc !== null) {
            $config['dispatch_func'] = $dispatchFunc;
        }

        return $config;
    }

    private function buildDefaultDispatchFunction(): ?callable {
        $strategy = $this->getCyclicJobsStrategy();

        $swooleConfig = $this->config->SWOOLE ?? new \stdClass();
        $workerNum = (int)($swooleConfig->worker_num ?? \swoole_cpu_num());

        if ($strategy !== 'DEDICATED_WORKER') {
            return null;
        }

        if ($workerNum < 2) {
            error_log("[SwooleApp] DEDICATED_WORKER требует минимум 2 воркера, используется стандартный диспатчер");
            return null;
        }

        error_log(sprintf(
            "[SwooleApp] Настройка диспатчера для DEDICATED_WORKER: worker 0 НЕ получает запросы, " .
            "запросы распределяются между воркерами 1-%d",
            $workerNum - 1
        ));

        return function ($server, $fd, $type, $data) use ($workerNum) {
            static $requestCount = 0;
            $requestCount++;

            $seed = $requestCount % 1000;
            $otherWorkersCount = $workerNum - 1;

            return 1 + ($seed % $otherWorkersCount);
        };
    }

    public function getCyclicJobsStrategy(): string {
        $envStrategy = $this->envVariables['SWOOLE_APP_CYCLIC_JOBS_STRATEGY'] ?? null;
        if ($envStrategy !== null && $envStrategy !== '') {
            return strtoupper(trim($envStrategy));
        }

        $cyclicJobs = $this->config->cyclic_jobs ?? null;
        if ($cyclicJobs !== null && isset($cyclicJobs->strategy)) {
            return strtoupper(trim($cyclicJobs->strategy));
        }

        return 'ALL_WORKERS';
    }

    /**
     * @param string $envKey
     * @param string $configPath
     * @param mixed $defaultValue
     * @return mixed
     */
    private function getConfigValue(string $envKey, string $configPath, mixed $defaultValue): mixed {
        $envValue = $this->envVariables[$envKey] ?? null;
        if ($envValue !== null && $envValue !== '') {
            return $this->parseValue($envValue);
        }

        $parts = explode('->', $configPath);
        $current = $this->config;
        foreach ($parts as $part) {
            if (!is_object($current) || !isset($current->$part)) {
                return $defaultValue;
            }
            $current = $current->$part;
        }

        if ($current !== null && $current !== '') {
            return $this->parseValue($current);
        }

        return $defaultValue;
    }

    public function getServerHost(): string {
        return (string) $this->getConfigValue(
            'SWOOLE_APP_SERVER_HOST',
            'server->host',
            '0.0.0.0'
        );
    }

    public function getServerPort(): int {
        return (int) $this->getConfigValue(
            'SWOOLE_APP_SERVER_PORT',
            'server->port',
            9501
        );
    }

    public function getServerMode(): int {
        $value = $this->getConfigValue(
            'SWOOLE_APP_SERVER_MODE',
            'server->mode',
            'PROCESS'
        );

        $mode = strtoupper(trim($value));
        if ($mode === 'BASE') {
            return SWOOLE_BASE;
        }

        return SWOOLE_PROCESS;
    }
}