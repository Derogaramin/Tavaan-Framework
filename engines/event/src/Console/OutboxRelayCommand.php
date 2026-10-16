<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Console;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Event\Outbox\DatabaseOutbox;

/**
 * تَوان | Tavaan — Event
 * OutboxRelayCommand — انتشار رویدادهای outbox (ADR-0009 — At-Least-Once).
 *
 * `tvn event:outbox-relay --limit=100`
 *
 * @api 1.0
 * @see docs/adr/ADR-0009-outbox-pattern.md
 */
final class OutboxRelayCommand extends Command
{
    protected string $signature = 'event:outbox-relay {--limit=100 : اندازهٔ دسته} {--release-stale=300 : آزادسازی claimهای قدیمی (ثانیه)}';

    protected string $description = 'Relay رویدادهای Transactional Outbox (انتشار محلی)';

    public function handle(): int
    {
        if (!$this->app->bound(ConnectionInterface::class)) {
            $this->error('موتور database در دسترس نیست.');

            return self::FAILURE;
        }

        $outbox = new DatabaseOutbox($this->app->make(ConnectionInterface::class));

        $stale = (int) ($this->option('release-stale') ?? 300);
        if ($stale > 0) {
            $released = $outbox->releaseStale($stale);
            if ($released > 0) {
                $this->warn(\sprintf('%d claim قدیمی آزاد شد.', $released));
            }
        }

        $dispatcher = $this->app->make(DispatcherInterface::class);
        $limit = \max(1, (int) ($this->option('limit') ?? 100));

        $batch = $outbox->fetchPending($limit);
        if ($batch === []) {
            $this->info('رویداد بازی در outbox نیست.');

            return self::SUCCESS;
        }

        $done = 0;
        foreach ($batch as $row) {
            try {
                $payload = \json_decode((string) ($row['payload'] ?? ''), true) ?? [];
                $dispatcher->dispatch((string) $row['event_name'], $payload);
                $outbox->markDone((int) $row['id']);
                $done++;
            } catch (\Throwable) {
                $outbox->markFailed((int) $row['id']);
            }
        }

        $this->info(\sprintf('%d از %d رویداد منتشر شد.', $done, \count($batch)));

        return self::SUCCESS;
    }
}
