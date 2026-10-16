<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Engine\Queue\Worker;
use Tavaan\Engine\Queue\WorkerOptions;

/**
 * تَوان | Tavaan — Queue
 * QueueWorkCommand — اجرای worker صف (DOC-061 §11).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work
        {connection? : نام اتصال صف}
        {--queue=default : صف‌ها با اولویت (جداشده با ویرگول)}
        {--once : فقط یک job پردازش شود}
        {--stop-when-empty : خروج وقتی صف خالی شد}
        {--max-jobs=0 : حداکثر job پیش از خروج}
        {--max-time=0 : حداکثر ثانیه پیش از خروج}
        {--memory=128 : حداکثر حافظهٔ MB پیش از recycle}
        {--tries=3 : حداکثر تلاش jobها}
        {--timeout=60 : timeout هر job (ثانیه)}
        {--sleep=3 : خواب (ثانیه) وقتی صف خالی است}
        {--backoff=0 : تأخیر پیش‌فرض retry (ثانیه)}
        {--daemon : حالت daemon (خروج از فرآیند در پایان)}';

    protected string $description = 'اجرای worker صف (پردازش jobها)';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);

        $connection = $this->argument('connection');

        $options = WorkerOptions::fromArray([
            'connectionName' => \is_string($connection) && $connection !== '' ? $connection : '',
            'queue' => (string) ($this->option('queue') ?? 'default'),
            'maxJobs' => (int) ($this->option('max-jobs') ?? 0),
            'maxTime' => (int) ($this->option('max-time') ?? 0),
            'maxMemory' => (int) ($this->option('memory') ?? 128),
            'sleep' => (float) ($this->option('sleep') ?? 3),
            'maxTries' => (int) ($this->option('tries') ?? 3),
            'timeout' => (int) ($this->option('timeout') ?? 60),
            'backoff' => (int) ($this->option('backoff') ?? 0),
            'stopWhenEmpty' => (bool) $this->option('stop-when-empty'),
        ]);

        /** @var Worker $worker */
        $worker = $this->app->make(Worker::class);

        $this->info(\sprintf(
            'worker صف آغاز شد (اتصال: %s، صف‌ها: %s) — SIGTERM/SIGINT برای خروج آرام.',
            $options->connectionName !== '' ? $options->connectionName : $manager->getDefaultConnection(),
            $options->queue,
        ));

        if ($this->option('once')) {
            $worker->runNextJob($options);

            return self::SUCCESS;
        }

        if ($this->option('daemon')) {
            $worker->daemon($options);
        }

        return $worker->run($options);
    }
}
