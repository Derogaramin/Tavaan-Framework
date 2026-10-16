<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Events\JobFailed;
use Tavaan\Engine\Queue\Events\JobProcessed;
use Tavaan\Engine\Queue\Events\JobProcessing;
use Tavaan\Engine\Queue\Events\JobReleased;
use Tavaan\Engine\Queue\Events\WorkerStopping;
use Tavaan\Engine\Queue\Exceptions\JobTimedOutException;
use Tavaan\Engine\Queue\Exceptions\MaxAttemptsExceededException;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Queue
 * Worker — حلقهٔ پردازش مقاوم (DOC-061 §4).
 *
 * - Graceful Shutdown با SIGTERM/SIGINT (job جاری تمام می‌شود، سپس خروج)
 * - Retry با backoff نمایی/فهرستی؛ شکست نهایی → failed_jobs + DLQ
 * - Timeout با pcntl_alarm (POSIX)؛ بدون pcntl → بررسی پس‌از‌اجرا
 * - Recycle حافظه (maxMemory) و سیگنال queue:restart
 * - Rate Limiting با cache
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class Worker
{
    /** کلید cache سیگنال restart */
    public const RESTART_CACHE_KEY = 'tvn:queue:restart';

    private bool $shouldStop = false;

    private int $stopStatus = 0;

    private string $stopReason = '';

    private int $processedJobs = 0;

    private int $failedJobs = 0;

    private float $startedAt = 0.0;

    private int $lastRestartSignal = 0;

    private string $currentJobName = '';

    private int $currentTimeout = 0;

    private readonly RateLimiter $rateLimiter;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?ContainerInterface $container = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?CacheRepository $cache = null,
        private readonly ?LoggerInterface $logger = null,
        ?RateLimiter $rateLimiter = null,
    ) {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter($this->cache);
        $this->listenForSignals();
    }

    /**
     * حلقهٔ اصلی — تا شرط خروج (signal/maxJobs/maxTime/memory/restart).
     *
     * @return int کد خروج
     */
    public function run(WorkerOptions $options): int
    {
        $this->shouldStop = false;
        $this->stopStatus = 0;
        $this->startedAt = \microtime(true);
        $this->lastRestartSignal = $this->restartSignal();

        $connection = $this->manager->connection($options->connectionName !== '' ? $options->connectionName : null);

        while (!$this->shouldStop) {
            if ($this->shouldQuit($options)) {
                $this->stop($this->stopStatus, $this->stopReason !== '' ? $this->stopReason : 'quit-condition');
                break;
            }

            $job = $this->getNextJob($connection, $options);

            if ($job === null) {
                if ($options->stopWhenEmpty) {
                    $this->stop(0, 'empty');
                    break;
                }
                $this->sleep($options->sleep);
                continue;
            }

            $this->processJob($job, $options);

            // 🔒 recycle حافظه
            if ($options->maxMemory > 0 && \memory_get_usage(true) > $options->maxMemory * 1024 * 1024) {
                $this->stop(0, 'memory');
            }
        }

        $this->events?->dispatch(new WorkerStopping($this->stopStatus, $this->stopReason));

        return $this->stopStatus;
    }

    /**
     * حالت daemon — پس از اتمام run خروج از فرآیند (DOC-061 §14).
     */
    public function daemon(WorkerOptions $options): never
    {
        exit($this->run($options));
    }

    /**
     * پردازش **یک** job بعدی (برای `queue:work --once` و تست).
     *
     * @return bool آیا jobای پردازش شد؟
     */
    public function runNextJob(WorkerOptions $options): bool
    {
        $connection = $this->manager->connection($options->connectionName !== '' ? $options->connectionName : null);
        $job = $this->getNextJob($connection, $options);

        if ($job === null) {
            return false;
        }

        $this->processJob($job, $options);

        return true;
    }

    /** درخواست خروج — job جاری تمام می‌شود، سپس حلقه می‌شکند. */
    public function stop(int $status = 0, string $reason = ''): void
    {
        $this->shouldStop = true;
        $this->stopStatus = $status;
        $this->stopReason = $reason;
    }

    public function processedJobs(): int
    {
        return $this->processedJobs;
    }

    public function failedJobs(): int
    {
        return $this->failedJobs;
    }

    // ─── پردازش job ───────────────────────────────────────────────────────

    /** @param \Tavaan\Core\Contracts\Queue\QueueInterface $connection */
    private function getNextJob(mixed $connection, WorkerOptions $options): ?JobInterface
    {
        foreach ($options->queueNames() as $queue) {
            try {
                $job = $connection->pop($queue);
            } catch (\Throwable $e) {
                $this->logger?->error('queue.pop_failed', ['queue' => $queue, 'error' => $e->getMessage()]);
                $this->sleep($options->sleep);

                continue;
            }

            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }

    private function processJob(JobInterface $job, WorkerOptions $options): void
    {
        $this->currentJobName = $job->getName();

        try {
            $this->events?->dispatch(new JobProcessing((string) $job->getConnectionName(), $job));

            // Rate Limit (DOC-061 §3)
            $limit = $job->rateLimit();
            if ($limit !== null && !$this->rateLimiter->attempt($limit)) {
                $delay = $this->rateLimiter->retryAfter($limit);
                $job->release($delay);
                $this->events?->dispatch(new JobReleased((string) $job->getConnectionName(), $job, $delay));

                return;
            }

            // بررسی تلاش‌ها / retryUntil پیش از اجرا
            $this->assertWithinLimits($job, $options);

            $this->fireWithTimeout($job, $options);

            if (!$job->isDeletedOrReleased()) {
                $job->delete();
            }

            $this->processedJobs++;
            $this->events?->dispatch(new JobProcessed((string) $job->getConnectionName(), $job));
        } catch (MaxAttemptsExceededException|JobTimedOutException $e) {
            $this->failedJobs++;
            $this->failJob($job, $e);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $e, $options);
        } finally {
            $this->clearTimeoutAlarm();
        }
    }

    /** اجرای job با timeout (pcntl_alarm یا بررسی پس‌از‌اجرا). */
    private function fireWithTimeout(JobInterface $job, WorkerOptions $options): void
    {
        $timeout = $job->timeout() ?? ($options->timeout > 0 ? $options->timeout : 0);
        $this->currentTimeout = $timeout;

        if ($timeout > 0 && \function_exists('pcntl_alarm')) {
            \pcntl_signal(\SIGALRM, function (): void {
                throw new JobTimedOutException($this->currentJobName, $this->currentTimeout);
            });
            \pcntl_alarm($timeout);
        }

        $started = \microtime(true);

        try {
            $job->fire();
        } finally {
            $this->clearTimeoutAlarm();
        }

        // بدون pcntl: بررسی cooperative پس از اجرا
        if ($timeout > 0 && !\function_exists('pcntl_alarm')
            && (\microtime(true) - $started) > $timeout) {
            throw new JobTimedOutException($this->currentJobName, $timeout);
        }
    }

    /** 🔒 تلاش بیش از حد / انقضای retryUntil → شکست نهایی. */
    private function assertWithinLimits(JobInterface $job, WorkerOptions $options): void
    {
        $maxTries = $job->maxTries() ?? ($options->maxTries > 0 ? $options->maxTries : 0);

        if ($maxTries > 0 && $job->attempts() > $maxTries) {
            throw new MaxAttemptsExceededException($job->getName(), $maxTries);
        }

        $retryUntil = $job->retryUntil();
        if ($retryUntil !== null && Clock::timestamp() >= $retryUntil->getTimestamp()) {
            throw new MaxAttemptsExceededException($job->getName(), $job->attempts());
        }
    }

    /** شکست قابل retry → release با backoff؛ وگرنه شکست نهایی (DOC-061 §4). */
    private function handleFailure(JobInterface $job, \Throwable $e, WorkerOptions $options): void
    {
        $maxTries = $job->maxTries() ?? ($options->maxTries > 0 ? $options->maxTries : 0);

        $retryUntil = $job->retryUntil();
        $withinRetryUntil = $retryUntil === null || Clock::timestamp() < $retryUntil->getTimestamp();

        if ($withinRetryUntil && ($maxTries === 0 || $job->attempts() < $maxTries)) {
            $delay = $this->backoffDelay($job->attempts(), $job->backoff() ?? $options->backoff);
            $job->release($delay);

            $this->events?->dispatch(new JobReleased((string) $job->getConnectionName(), $job, $delay, $e));
            $this->logger?->warning('queue.job.released', [
                'job' => $job->getName(),
                'attempt' => $job->attempts(),
                'delay' => $delay,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $this->failedJobs++;
        $this->failJob($job, $maxTries > 0 && $job->attempts() >= $maxTries
            ? new MaxAttemptsExceededException($job->getName(), $maxTries)
            : $e);
    }

    /**
     * backoff نمایی (int) یا فهرستی (array) — DOC-061 §4.
     *
     * @param int|list<int>|null $backoff
     */
    private function backoffDelay(int $attempt, int|array|null $backoff): int
    {
        if (\is_array($backoff) && $backoff !== []) {
            return (int) ($backoff[$attempt - 1] ?? \end($backoff));
        }

        $base = (int) $backoff;
        if ($base <= 0) {
            return 0;
        }

        return $base * (2 ** \max(0, $attempt - 1));   // نمایی
    }

    /** شکست نهایی: failed_jobs + DLQ + hook کاربر + رویداد (DOC-061 §4). */
    private function failJob(JobInterface $job, \Throwable $e): void
    {
        $payload = $job->payload();

        try {
            $job->fail($e);
        } catch (\Throwable) {
            // حذف از backend ناموفق — ادامه می‌دهیم تا ثبت شکست از دست نرود
        }

        $uuid = (string) ($payload['uuid'] ?? '');

        try {
            $failed = $this->manager->failedProvider();
            $uuid = $failed->log(
                (string) $job->getConnectionName(),
                $job->getQueue(),
                $job->getRawPayload(),
                $e,
                $job->getJobId(),
            ) ?: $uuid;
        } catch (\Throwable $logError) {
            $this->logger?->error('queue.failed_log_error', ['error' => $logError->getMessage()]);
        }

        // DLQ (اختیاری — صف مرده برای بازبینی؛ worker پیش‌فرض آن را مصرف نمی‌کند)
        $this->pushToDeadLetterQueue($job);

        // hook کاربر failed() + ثبت شکست Batch از طریق wrapper
        $this->callWrapperFailed($payload, $e);

        $this->events?->dispatch(new JobFailed((string) $job->getConnectionName(), $job, $e, $uuid));

        $this->logger?->error('queue.job.failed', [
            'job' => $job->getName(),
            'uuid' => $uuid,
            'queue' => $job->getQueue(),
            'attempts' => $job->attempts(),
            'error' => $e->getMessage(),
        ]);
    }

    private function pushToDeadLetterQueue(JobInterface $job): void
    {
        $dlq = $this->manager->configValue('failed.dlq');
        if (!\is_string($dlq) || $dlq === '') {
            return;
        }

        try {
            $connection = $this->manager->connection($job->getConnectionName());
            if (\method_exists($connection, 'pushRaw')) {
                $connection->pushRaw($job->getRawPayload(), $dlq);
            }
        } catch (\Throwable $e) {
            $this->logger?->error('queue.dlq_failed', ['error' => $e->getMessage()]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function callWrapperFailed(array $payload, \Throwable $e): void
    {
        if ($this->container === null) {
            return;
        }

        try {
            $wrapperClass = (string) ($payload['job'] ?? '');
            if ($wrapperClass === '' || !\class_exists($wrapperClass)) {
                return;
            }
            $wrapper = $this->container->make($wrapperClass);
            if (\method_exists($wrapper, 'failed')) {
                $wrapper->failed($payload, $e);
            }
        } catch (\Throwable) {
            // 🔒 خطای hook نباید جریان شکست را بشکند
        }
    }

    // ─── شرایط خروج ───────────────────────────────────────────────────────

    private function shouldQuit(WorkerOptions $options): bool
    {
        if ($this->shouldStop) {
            return true;
        }

        if ($options->maxJobs > 0 && $this->processedJobs + $this->failedJobs >= $options->maxJobs) {
            $this->stopReason = 'max-jobs';

            return true;
        }

        if ($options->maxTime > 0 && (\microtime(true) - $this->startedAt) >= $options->maxTime) {
            $this->stopReason = 'max-time';

            return true;
        }

        // سیگنال queue:restart
        $signal = $this->restartSignal();
        if ($signal > $this->lastRestartSignal) {
            $this->stopStatus = 1;
            $this->stopReason = 'restart';

            return true;
        }

        return false;
    }

    private function restartSignal(): int
    {
        if ($this->cache === null) {
            return 0;
        }

        try {
            $value = $this->cache->get(self::RESTART_CACHE_KEY);

            return \is_numeric($value) ? (int) $value : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /** 🔒 Graceful Shutdown — SIGTERM/SIGINT (DOC-061 §4.1). */
    private function listenForSignals(): void
    {
        if (!\function_exists('pcntl_signal')) {
            return;
        }

        if (\function_exists('pcntl_async_signals')) {
            \pcntl_async_signals(true);
        }

        \pcntl_signal(\SIGTERM, function (): void {
            $this->stopReason = 'SIGTERM';
            $this->shouldStop = true;
        });
        \pcntl_signal(\SIGINT, function (): void {
            $this->stopReason = 'SIGINT';
            $this->shouldStop = true;
        });
    }

    private function clearTimeoutAlarm(): void
    {
        if (\function_exists('pcntl_alarm')) {
            \pcntl_alarm(0);
        }
        if (\function_exists('pcntl_signal')) {
            \pcntl_signal(\SIGALRM, \SIG_DFL);
        }
    }

    private function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            \usleep(1000);   // حداقل فاصله برای جلوگیری از hot-spin

            return;
        }

        \usleep((int) ($seconds * 1_000_000));
    }
}
