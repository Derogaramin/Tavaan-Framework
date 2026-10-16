<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Engine\Console\ConsoleKernel;
use Tavaan\Engine\Console\Output\Output;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Schedule
 * ScheduleManager — ثبت و اجرای رویدادهای زمان‌بندی‌شده (DOC-062 §2/§4).
 *
 * یک entry در crontab کافی است:
 * ```
 * * * * * * php /path/bin/tvn schedule:run >> /dev/null 2>&1
 * ```
 *
 * 🔒 Overlap Prevention با mutex اتمیک (cache->add) و onOneServer
 * با قفل دقیقه‌ای (DOC-062 §4.1/§4.2).
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class ScheduleManager
{
    /** @var list<ScheduleEvent> */
    private array $events = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private readonly ?ConfigRepository $config = null,
        private readonly ?CacheRepository $cache = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    // ─── ثبت (DOC-062 §2) ─────────────────────────────────────────────────

    /**
     * زمان‌بندی یک دستور CLI.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function command(string $command, array $arguments = []): ScheduleEvent
    {
        return $this->add(new ScheduleEvent('command', $command, $arguments));
    }

    /** زمان‌بندی یک job صف‌روند (در لحظهٔ اجرا به queue سپرده می‌شود). */
    public function job(object $job): ScheduleEvent
    {
        return $this->add(new ScheduleEvent('job', $job));
    }

    /** زمان‌بندی یک closure. */
    public function call(callable $callback): ScheduleEvent
    {
        return $this->add(new ScheduleEvent('call', $callback));
    }

    /** زمان‌بندی یک دستور shell خارجی. */
    public function exec(string $command): ScheduleEvent
    {
        return $this->add(new ScheduleEvent('exec', $command));
    }

    public function add(ScheduleEvent $event): ScheduleEvent
    {
        $this->events[] = $event;

        return $event;
    }

    /** @return list<ScheduleEvent> */
    public function events(): array
    {
        return $this->events;
    }

    public function defaultTimezone(): string
    {
        $tz = $this->config?->get('schedule.timezone', 'Asia/Tehran');

        return \is_string($tz) && $tz !== '' ? $tz : (string) (\date_default_timezone_get() ?: 'UTC');
    }

    // ─── اجرا (DOC-062 §4) ────────────────────────────────────────────────

    /**
     * اجرای چرخهٔ زمان‌بندی — توسط `schedule:run` (هر دقیقه).
     *
     * @return list<array{event: ScheduleEvent, status: string, exit: int, output: string, ms: int}>
     */
    public function run(?\DateTimeImmutable $now = null): array
    {
        $now ??= Clock::now();
        $results = [];
        $inMaintenance = $this->inMaintenanceMode();

        foreach ($this->events as $event) {
            $local = $this->localTime($event, $now);

            // رویداد بدون timezone صریح → با timezone پیش‌فرض manager سنجیده می‌شود
            $eventNow = $event->timezoneName() !== null
                ? $now
                : $now->setTimezone(new \DateTimeZone($this->defaultTimezone()));

            if (!$event->isDue($eventNow)) {
                continue;
            }

            if ($inMaintenance && !$event->runsInMaintenanceMode()) {
                $results[] = $this->result($event, 'skipped-maintenance', 0, '');
                continue;
            }

            if (!$event->filtersPass()) {
                $results[] = $this->result($event, 'skipped-filter', 0, '');
                continue;
            }

            // 🔒 onOneServer: فقط اولین سرور (DOC-062 §4.2)
            if ($event->wantsOnOneServer() && !$this->acquireServerLock($event, $local)) {
                $results[] = $this->result($event, 'skipped-other-server', 0, '');
                continue;
            }

            // 🔒 withoutOverlapping: mutex اتمیک (DOC-062 §4.1)
            if ($event->wantsWithoutOverlapping() && !$this->acquireMutex($event)) {
                $results[] = $this->result($event, 'skipped-overlapping', 0, '');
                continue;
            }

            $started = \microtime(true);
            [$status, $exit, $output] = $this->dispatchEvent($event);
            $ms = (int) \round((\microtime(true) - $started) * 1000);

            if ($event->wantsWithoutOverlapping() && !$event->wantsBackground()) {
                $this->releaseMutex($event);
            }

            $this->fireCallbacks($event, $exit);
            $this->writeOutput($event, $output);

            if ($status === 'failed') {
                $this->logger?->error('schedule.task.failed', ['task' => $event->summary(), 'exit' => $exit]);
            }

            $results[] = $this->result($event, $status, $exit, $output, $ms);
        }

        return $results;
    }

    // ─── Mutex / Lock ─────────────────────────────────────────────────────

    /** 🔒 add اتمیک است — اجرای همزمان دومین نمونه را رد می‌کند. */
    private function acquireMutex(ScheduleEvent $event): bool
    {
        if ($this->cache === null) {
            return true;    // بدون cache → degrade ایمن (اجرا)
        }

        return $this->cache->add($event->mutexName(), true, $event->overlapTtlSeconds());
    }

    private function releaseMutex(ScheduleEvent $event): void
    {
        $this->cache?->forget($event->mutexName());
    }

    /** 🔒 فقط اولین سرور در هر دقیقه قفل را می‌گیرد. */
    private function acquireServerLock(ScheduleEvent $event, \DateTimeImmutable $local): bool
    {
        if ($this->cache === null) {
            return true;
        }

        return $this->cache->add($event->serverLockName($local), (string) (\gethostname() ?: 'unknown'), 3600);
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────

    /** @return array{0: string, 1: int, 2: string} [status, exit, output] */
    private function dispatchEvent(ScheduleEvent $event): array
    {
        try {
            return match ($event->type()) {
                'command' => $this->runCommand($event),
                'call' => $this->runCall($event),
                'job' => $this->runJob($event),
                'exec' => $this->runExec($event),
                default => ['failed', 1, ''],
            };
        } catch (\Throwable $e) {
            $this->logger?->error('schedule.dispatch_error', ['task' => $event->summary(), 'error' => $e->getMessage()]);

            return ['failed', 1, $e->getMessage()];
        }
    }

    /** @return array{0: string, 1: int, 2: string} */
    private function runCommand(ScheduleEvent $event): array
    {
        $command = (string) $event->task();

        // 🔒 schedule:run زمان‌بندی‌شدنی نیست:
        // - درون‌فرآیندی → بازگشت بی‌پایان («Maximum call stack size exceeded»)
        // - background   → هر اجرا یک schedule:run جدید زایش می‌دهد (انفجار فرآیند)
        if ($command === 'schedule:run') {
            return ['failed', 1, 'schedule:run cannot be scheduled (infinite recursion guard)'];
        }

        if ($event->wantsBackground()) {
            // subprocess: php bin/tvn {command} — خروجی به فایل/nohup
            $script = $this->entryScript();
            $line = \sprintf('%s %s %s', \escapeshellarg(\PHP_BINARY), \escapeshellarg($script), $command);
            $this->spawnBackground($line, $event->outputPath());

            return ['background', 0, ''];
        }

        $kernel = $this->kernel();
        if ($kernel === null) {
            return ['failed', 1, 'console kernel unavailable'];
        }

        // اجرا درون‌فرآیندی با خروجی قابل ضبط
        $stream = \fopen('php://temp', 'w+b');
        $exit = $stream === false
            ? $kernel->call($command, $event->arguments())
            : $this->callWithStream($kernel, $command, $event->arguments(), $stream);

        $output = '';
        if (\is_resource($stream)) {
            \rewind($stream);
            $output = (string) \stream_get_contents($stream);
            \fclose($stream);
        }

        return [$exit === 0 ? 'finished' : 'failed', $exit, $output];
    }

    /**
     * اجرای دستور با Output روی stream (ضبط خروجی).
     *
     * @param array<int|string, mixed> $arguments
     * @param resource                 $stream
     */
    private function callWithStream(ConsoleKernel $kernel, string $command, array $arguments, $stream): int
    {
        $tokens = [];
        foreach ($arguments as $key => $value) {
            if (\is_string($key) && \str_starts_with($key, '--')) {
                $tokens[] = $value === true ? $key : $key . '=' . $value;
                continue;
            }
            $tokens[] = (string) $value;
        }

        try {
            $resolved = $kernel->resolve($command);
        } catch (\Throwable) {
            return $kernel->call($command, $arguments);   // مسیر استاندارد (خطا/پیشنهاد توسط kernel)
        }

        if ($this->container === null) {
            return $kernel->call($command, $arguments);
        }

        return $resolved->run($this->container, $tokens, new Output($stream, false));
    }

    /** @return array{0: string, 1: int, 2: string} */
    private function runCall(ScheduleEvent $event): array
    {
        $callback = $event->task();
        if (!\is_callable($callback)) {
            return ['failed', 1, ''];
        }

        $result = $this->container !== null
            ? $this->container->call($callback)
            : $callback();

        return ['finished', 0, \is_scalar($result) ? (string) $result : ''];
    }

    /** @return array{0: string, 1: int, 2: string} */
    private function runJob(ScheduleEvent $event): array
    {
        $job = $event->task();
        if (!\is_object($job)) {
            return ['failed', 1, 'job باید شیء باشد'];
        }

        if ($this->container === null || !$this->container->bound('queue')) {
            return ['failed', 1, 'موتور queue برای Schedule::job لازم است'];
        }

        $queue = $this->container->make('queue');
        $connection = $event->connectionName() !== null ? $queue->connection($event->connectionName()) : $queue->connection();

        if ($event->queueName() !== null) {
            $connection->pushOn($event->queueName(), $job);
        } else {
            $connection->push($job);
        }

        return ['queued', 0, ''];
    }

    /** @return array{0: string, 1: int, 2: string} */
    private function runExec(ScheduleEvent $event): array
    {
        $line = (string) $event->task();

        if ($event->wantsBackground()) {
            $this->spawnBackground($line, $event->outputPath());

            return ['background', 0, ''];
        }

        if (!\function_exists('proc_open')) {
            $output = (string) \shell_exec($line . ' 2>&1');

            return ['finished', 0, $output];
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = \proc_open($line, $descriptors, $pipes);
        if (!\is_resource($process)) {
            return ['failed', 1, ''];
        }

        $output = (string) \stream_get_contents($pipes[1]);
        $error = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exit = \proc_close($process);

        return [$exit === 0 ? 'finished' : 'failed', $exit, $output . $error];
    }

    /** اجرای پس‌زمینه (بدون انتظار) — خروجی به فایل. */
    private function spawnBackground(string $line, ?string $outputPath): void
    {
        $redirect = $outputPath !== null ? ' >> ' . \escapeshellarg($outputPath) . ' 2>&1' : ' > /dev/null 2>&1';

        if (\function_exists('proc_open')) {
            $process = @\proc_open($line . $redirect . ' &', [1 => ['file', '/dev/null', 'a']], $pipes);
            if (\is_resource($process)) {
                \proc_close($process);

                return;
            }
        }

        @\exec($line . $redirect . ' &');
    }

    // ─── متفرقه ───────────────────────────────────────────────────────────

    private function fireCallbacks(ScheduleEvent $event, int $exit): void
    {
        $callbacks = $exit === 0 ? $event->successCallbacks() : $event->failureCallbacks();

        foreach ($callbacks as $callback) {
            try {
                if ($this->container !== null) {
                    $this->container->call($callback);
                } else {
                    $callback();
                }
            } catch (\Throwable $e) {
                $this->logger?->error('schedule.callback_error', ['task' => $event->summary(), 'error' => $e->getMessage()]);
            }
        }
    }

    private function writeOutput(ScheduleEvent $event, string $output): void
    {
        $path = $event->outputPath();
        if ($path === null || $output === '' || $event->wantsBackground()) {
            return;     // background خودش redirect دارد
        }

        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }
        \file_put_contents($path, $output, \FILE_APPEND | \LOCK_EX);
    }

    private function inMaintenanceMode(): bool
    {
        if ((bool) ($this->config?->get('schedule.run_in_maintenance', false) ?? false)) {
            return false;
        }

        $downFile = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/down' : '';

        return $downFile !== '' && \is_file($downFile);
    }

    private function localTime(ScheduleEvent $event, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $timezone = $event->timezoneName() ?? $this->defaultTimezone();

        return $now->setTimezone(new \DateTimeZone($timezone));
    }

    private function kernel(): ?ConsoleKernel
    {
        if ($this->container === null || !$this->container->bound(ConsoleKernel::class)) {
            return null;
        }

        $kernel = $this->container->make(ConsoleKernel::class);

        return $kernel instanceof ConsoleKernel ? $kernel : null;
    }

    private function entryScript(): string
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname(__DIR__, 3);

        return $base . '/bin/tvn';
    }

    /**
     * @return array{event: ScheduleEvent, status: string, exit: int, output: string, ms: int}
     */
    private function result(ScheduleEvent $event, string $status, int $exit, string $output, int $ms = 0): array
    {
        return ['event' => $event, 'status' => $status, 'exit' => $exit, 'output' => $output, 'ms' => $ms];
    }
}
