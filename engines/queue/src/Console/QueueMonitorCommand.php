<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * QueueMonitorCommand — پایش اندازهٔ صف‌ها در برابر آستانه (DOC-061 §11).
 *
 * خروجی برای monitoring خارجی (exit code 1 = عبور از آستانه).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueMonitorCommand extends Command
{
    protected string $signature = 'queue:monitor
        {queues : صف‌ها با connection — مثل `redis:default,redis:mail`}
        {--max=100 : آستانهٔ هشدار}';

    protected string $description = 'پایش اندازهٔ صف‌ها (هشدار وقتی از آستانه گذشت)';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);

        $max = (int) ($this->option('max') ?? 100);
        $entries = \explode(',', (string) $this->argument('queues'));

        $rows = [];
        $exceeded = false;

        foreach ($entries as $entry) {
            $entry = \trim($entry);
            if ($entry === '') {
                continue;
            }

            [$connectionName, $queue] = \array_pad(\explode(':', $entry, 2), 2, null);
            $queue ??= 'default';

            try {
                $size = $manager->connection($connectionName !== '' ? $connectionName : null)->size($queue);
            } catch (\Throwable $e) {
                $rows[] = [$entry, 'خطا: ' . $e->getMessage(), (string) $max, '⚠️'];
                $exceeded = true;
                continue;
            }

            $ok = $size <= $max;
            $exceeded = $exceeded || !$ok;
            $rows[] = [$entry, (string) $size, (string) $max, $ok ? '✅' : '🔴'];
        }

        $this->table(['صف', 'اندازه', 'آستانه', 'وضعیت'], $rows);

        if ($exceeded) {
            $this->warn('یک یا چند صف از آستانه عبور کردند — workerها را بررسی کنید.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
