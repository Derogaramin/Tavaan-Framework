<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Queue
 * QueueListenCommand — listener با subprocess (بازراه‌اندازی پس از هر خروج
 * worker → تغییرات کد بدون restart دستی؛ DOC-061 §11).
 *
 * 🔒 بدون proc_open → fallback به worker درون‌فرآیندی.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueListenCommand extends Command
{
    protected string $signature = 'queue:listen
        {connection? : نام اتصال صف}
        {--queue=default : صف‌ها با اولویت (جداشده با ویرگول)}
        {--tries=3 : حداکثر تلاش jobها}
        {--timeout=60 : timeout هر job (ثانیه)}
        {--sleep=3 : خواب (ثانیه) وقتی صف خالی است}
        {--max-time=0 : حداکثر عمر هر subprocess (ثانیه؛ ۰ = پیش‌فرض ۳۶۰۰)}';

    protected string $description = 'اجرای listener صف (worker در subprocess با بازراه‌اندازی خودکار)';

    public function handle(): int
    {
        if (!\function_exists('proc_open')) {
            $this->warn('proc_open در دسترس نیست — اجرای درون‌فرآیندی (بدون reload کد).');

            return $this->runInProcess();
        }

        $connection = $this->argument('connection');
        $maxTime = (int) ($this->option('max-time') ?? 0) ?: 3600;

        $argv = [
            \PHP_BINARY,
            $this->entryScript(),
            'queue:work',
            '--queue=' . (string) ($this->option('queue') ?? 'default'),
            '--tries=' . (string) ($this->option('tries') ?? 3),
            '--timeout=' . (string) ($this->option('timeout') ?? 60),
            '--sleep=' . (string) ($this->option('sleep') ?? 3),
            '--max-time=' . $maxTime,
        ];
        if (\is_string($connection) && $connection !== '') {
            $argv[] = (string) $connection;
        }

        $this->info('listener صف آغاز شد — SIGTERM/SIGINT برای توقف.');
        $this->listenForStop();

        while (!$this->stopRequested()) {
            $status = $this->runChild($argv);

            // کد ۱ = restart signal یا خطا — در هر صورت subprocess دوباره بالا می‌آید
            if ($status !== 0) {
                $this->comment(\sprintf('worker با کد %d خارج شد — بازراه‌اندازی…', $status));
            }
            \usleep(200_000);
        }

        $this->info('listener متوقف شد.');

        return self::SUCCESS;
    }

    /** @param list<string> $argv */
    private function runChild(array $argv): int
    {
        $descriptor = [1 => \STDOUT, 2 => \STDERR];
        $process = @\proc_open($argv, $descriptor, $pipes);
        if (!\is_resource($process)) {
            $this->error('راه‌اندازی subprocess ناموفق بود.');

            return self::FAILURE;
        }

        return \proc_close($process);
    }

    private function runInProcess(): int
    {
        /** @var \Tavaan\Engine\Queue\Worker $worker */
        $worker = $this->app->make(\Tavaan\Engine\Queue\Worker::class);

        return $worker->run(\Tavaan\Engine\Queue\WorkerOptions::fromArray([
            'connectionName' => (string) ($this->argument('connection') ?? ''),
            'queue' => (string) ($this->option('queue') ?? 'default'),
            'maxTries' => (int) ($this->option('tries') ?? 3),
            'timeout' => (int) ($this->option('timeout') ?? 60),
            'sleep' => (float) ($this->option('sleep') ?? 3),
        ]));
    }

    private function entryScript(): string
    {
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 5);

        return $base . '/bin/tvn';
    }

    private bool $stop = false;

    private function listenForStop(): void
    {
        if (!\function_exists('pcntl_signal') || !\function_exists('pcntl_async_signals')) {
            return;
        }

        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, function (): void {
            $this->stop = true;
        });
        \pcntl_signal(\SIGINT, function (): void {
            $this->stop = true;
        });
    }

    private function stopRequested(): bool
    {
        return $this->stop;
    }
}
