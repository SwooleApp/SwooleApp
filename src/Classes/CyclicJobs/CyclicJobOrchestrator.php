<?php

namespace Sidalex\SwooleApp\Classes\CyclicJobs;

use Sidalex\SwooleApp\Classes\Constants\CyclicJobsDistributionStrategy;
use Sidalex\SwooleApp\Classes\Wrapper\ConfigWrapper;
use Swoole\Coroutine;
use Swoole\Http\Server;

class CyclicJobOrchestrator
{
    private array $jobs;
    private ConfigWrapper $config;
    private int $workerId;
    private Server $server;
    private array $runningJobs = [];
    private bool $shuttingDown = false;
    private array $jobStats = [];
    private bool $isTaskWorker;
    private string $workerType;

    private const DEFAULT_MONITORING_INTERVAL = 60;
    private const ERROR_RETRY_DELAY = 5;

    public function __construct(array $jobs, ConfigWrapper $config, int $workerId, Server $server)
    {
        $this->jobs = $jobs;
        $this->config = $config;
        $this->workerId = $workerId;
        $this->server = $server;
        $this->isTaskWorker = $server->taskworker;
        $this->workerType = $this->isTaskWorker ? 'task_worker' : 'http_worker';
    }

    /**
     * Запуск циклических задач
     */
    public function start(): void
    {
        // Критическая проверка: НЕ запускаем в task-воркерах
        if ($this->isTaskWorker) {
            error_log(sprintf(
                "[Worker %d][task_worker] Циклические задачи не запускаются в task-воркерах",
                $this->workerId
            ));
            return;
        }

        $strategy = $this->getDistributionStrategy();

        // Безопасно получаем количество HTTP воркеров
        $totalWorkers = (int)($this->server->setting['worker_num'] ?? swoole_cpu_num());

        $this->logStrategyInfo($strategy, $totalWorkers);

        $jobsStarted = 0;
        foreach ($this->jobs as $index => $job) {
            if ($this->shouldRunJob($strategy, $index, $totalWorkers)) {
                $this->startJob($job, $index);
                $jobsStarted++;
            }
        }

        error_log(sprintf(
            "[Worker %d][http_worker] Запущено циклических задач: %d",
            $this->workerId,
            $jobsStarted
        ));

        if ($jobsStarted > 0) {
            $this->startMonitoring();
        }
    }

    /**
     * Логирование информации о стратегии
     */
    private function logStrategyInfo(CyclicJobsDistributionStrategy $strategy, int $totalWorkers): void
    {
        $strategyName = match ($strategy) {
            CyclicJobsDistributionStrategy::ALL_WORKERS => 'ALL_WORKERS',
            CyclicJobsDistributionStrategy::DEDICATED_WORKER => 'DEDICATED_WORKER',
            CyclicJobsDistributionStrategy::ROUND_ROBIN => 'ROUND_ROBIN',
        };

        $logMsg = sprintf(
            "[Worker %d][http_worker] Стратегия: %s, всего HTTP воркеров: %d",
            $this->workerId,
            $strategyName,
            $totalWorkers
        );

        if ($strategy === CyclicJobsDistributionStrategy::DEDICATED_WORKER) {
            if ($this->workerId === 0) {
                $dedicatedLoad = $this->config->getConfigFromKey('cyclic_jobs.dedicated_worker_load') ?? 0.1;
                $logMsg .= sprintf(", ВЫДЕЛЕННЫЙ ВОРКЕР (нагрузка: %.1f%%)", $dedicatedLoad * 100);
            } else {
                $logMsg .= " - задачи не запускаются (только worker 0)";
            }
        }

        error_log($logMsg);
    }

    /**
     * Проверка, должен ли воркер запускать задачу
     */
    private function shouldRunJob(
        CyclicJobsDistributionStrategy $strategy,
        int                            $jobIndex,
        int                            $totalWorkers
    ): bool
    {
        // Всегда проверяем, что это не task воркер
        if ($this->isTaskWorker) {
            return false;
        }

        return match ($strategy) {
            CyclicJobsDistributionStrategy::ALL_WORKERS => true,

            CyclicJobsDistributionStrategy::DEDICATED_WORKER =>
                $this->workerId === 0, // Только worker 0

            CyclicJobsDistributionStrategy::ROUND_ROBIN =>
                ($this->workerId % max(1, $totalWorkers)) === ($jobIndex % max(1, $totalWorkers)),
        };
    }

    /**
     * Запуск одной циклической задачи
     */
    private function startJob(CyclicJobsInterface $job, int $index): void
    {
        $jobClass = get_class($job);

        $this->jobStats[$index] = [
            'class' => $jobClass,
            'started_at' => time(),
            'worker_id' => $this->workerId,
            'worker_type' => $this->workerType,
            'runs' => 0,
            'errors' => 0,
            'restarts' => 0,
        ];

        $cid = Coroutine::create(function () use ($job, $index, $jobClass) {
            error_log(sprintf(
                "[Worker %d][http_worker] Запущена циклическая задача: %s (интервал: %.1fс, старт через: %.1fс)",
                $this->workerId,
                $jobClass,
                $job->getTimeSleepSecond(),
                $job->getStartupSleepSecond()
            ));

            // Начальная задержка
            Coroutine::sleep($job->getStartupSleepSecond());

            while (!$this->shuttingDown) {
                try {
                    $startTime = microtime(true);
                    $job->runJob();

                    $this->jobStats[$index]['runs']++;
                    $this->jobStats[$index]['last_run'] = time();
                    $this->jobStats[$index]['last_duration'] = microtime(true) - $startTime;

                    if ($this->config->getConfigFromKey('APP_DEBUG')) {
                        error_log(sprintf(
                            "[Worker %d][http_worker] Job %s выполнен за %.4fс",
                            $this->workerId,
                            $jobClass,
                            microtime(true) - $startTime
                        ));
                    }

                    $this->sleepWithInterrupt($job->getTimeSleepSecond());

                } catch (\Throwable $e) {
                    $this->handleJobError($e, $jobClass, $index);
                    Coroutine::sleep(self::ERROR_RETRY_DELAY);
                }
            }

            error_log(sprintf(
                "[Worker %d][http_worker] Job %s остановлен",
                $this->workerId,
                $jobClass
            ));
        });

        $this->runningJobs[$index] = $cid;
    }

    /**
     * Обработка ошибки задачи
     */
    private function handleJobError(\Throwable $e, string $jobClass, int $index): void
    {
        $errorMsg = sprintf(
            "[Worker %d][http_worker] Cyclic job %s failed: %s\n%s",
            $this->workerId,
            $jobClass,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        error_log($errorMsg);

        $this->jobStats[$index]['errors']++;
        $this->jobStats[$index]['last_error'] = [
            'time' => time(),
            'message' => $e->getMessage(),
        ];
    }

    /**
     * Сон с возможностью прерывания
     */
    private function sleepWithInterrupt(float $seconds): void
    {
        $chunks = (int)ceil($seconds);
        for ($i = 0; $i < $chunks; $i++) {
            if ($this->shuttingDown) {
                break;
            }
            Coroutine::sleep(min(1, $seconds - $i));
        }
    }

    /**
     * Запуск мониторинга задач
     */
    private function startMonitoring(): void
    {
        $interval = (int)$this->config->getConfigFromKey('cyclic_jobs.monitoring_interval')
            ?: self::DEFAULT_MONITORING_INTERVAL;

        Coroutine::create(function () use ($interval) {
            while (!$this->shuttingDown) {
                Coroutine::sleep($interval);
                $this->checkJobsHealth();

                if ($this->config->getConfigFromKey('APP_DEBUG')) {
                    $this->logStats();
                }
            }
        });
    }

    /**
     * Проверка здоровья задач
     */
    private function checkJobsHealth(): void
    {
        foreach ($this->runningJobs as $index => $cid) {
            if (!Coroutine::exists($cid)) {
                error_log(sprintf(
                    "[Worker %d][http_worker] Job %s упал, перезапуск...",
                    $this->workerId,
                    $this->jobStats[$index]['class'] ?? 'unknown'
                ));

                $this->jobStats[$index]['restarts']++;

                if (isset($this->jobs[$index])) {
                    $this->startJob($this->jobs[$index], $index);
                }
            }
        }
    }

    /**
     * Логирование статистики
     */
    private function logStats(): void
    {
        $stats = [
            'worker' => $this->workerId,
            'worker_type' => $this->workerType,
            'timestamp' => date('Y-m-d H:i:s'),
            'jobs' => $this->jobStats
        ];

        error_log(sprintf(
            "[Worker %d][http_worker] Cyclic jobs stats: %s",
            $this->workerId,
            json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ));
    }

    /**
     * Остановка всех задач
     */
    public function shutdown(): void
    {
        $this->shuttingDown = true;

        $start = time();
        $runningCount = count($this->runningJobs);

        while (!empty($this->runningJobs) && (time() - $start) < 30) {
            Coroutine::sleep(1);
        }

        error_log(sprintf(
            "[Worker %d][%s] Cyclic jobs shutdown complete. Jobs running: %d",
            $this->workerId,
            $this->workerType,
            $runningCount
        ));
    }

    /**
     * Получение стратегии распределения
     */
    private function getDistributionStrategy(): CyclicJobsDistributionStrategy
    {
        $strategy = strtoupper($this->config->getConfigFromKey('cyclic_jobs')->strategy ?? 'ALL_WORKERS');

        return $this->matchStrategy($strategy);
    }

    /**
     * Преобразование строки в стратегию
     */
    private function matchStrategy(string $strategy): CyclicJobsDistributionStrategy
    {
        return match (strtoupper(trim($strategy))) {
            'DEDICATED_WORKER' => CyclicJobsDistributionStrategy::DEDICATED_WORKER,
            'ROUND_ROBIN' => CyclicJobsDistributionStrategy::ROUND_ROBIN,
            default => CyclicJobsDistributionStrategy::ALL_WORKERS,
        };
    }

    /**
     * Получение информации о процессе
     */
    public function getProcessInfo(): array
    {
        return [
            'worker_id' => $this->workerId,
            'worker_type' => $this->workerType,
            'is_task_worker' => $this->isTaskWorker,
            'is_http_worker' => !$this->isTaskWorker,
        ];
    }
}