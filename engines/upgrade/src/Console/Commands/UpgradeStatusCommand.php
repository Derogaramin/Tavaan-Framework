<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Upgrade\Upgrader;

/**
 * `tvn upgrade:status` — نسخهٔ فعلی و مسیر ارتقا (DOC-081E §7).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeStatusCommand extends Command
{
    protected string $signature = 'upgrade:status {--to= : محاسبهٔ مسیر تا نسخهٔ هدف}';

    protected string $description = 'نسخهٔ جاری، نسخه‌های موجود و آخرین backup';

    public function handle(): int
    {
        /** @var Upgrader $upgrader */
        $upgrader = $this->app->make(Upgrader::class);

        $to = $this->option('to');
        $status = $upgrader->status(\is_string($to) && $to !== '' ? $to : null);

        $rows = [
            ['نسخهٔ جاری', (string) $status['current']],
            ['نسخه‌های دارای patch', $status['available'] === [] ? '—' : \implode('، ', $status['available'])],
            ['گام بعدی', (string) ($status['next'] ?? '—')],
            ['آخرین backup', (string) ($status['last_backup'] ?? '—')],
            ['نسخهٔ پیشین (rollback)', (string) ($status['previous_version'] ?? '—')],
            ['Maintenance', $upgrader->isDown() ? '🔻 down' : '✅ up'],
        ];
        if (\is_array($status['path'])) {
            $rows[] = ['مسیر تا ' . (string) $status['path_to'], \implode(' → ', $status['path'])];
        }

        $this->table(['کلید', 'مقدار'], $rows);

        return self::SUCCESS;
    }
}
