<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;
use Tavaan\Engine\Upgrade\Upgrader;

/**
 * `tvn upgrade --to=X.Y.Z` — ارتقای کامل سیستم (DOC-081E §7).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeCommand extends Command
{
    protected string $signature = 'upgrade {--to= : نسخهٔ هدف (X.Y.Z)} {--no-backup : بدون backup (فقط dev — نیازمند require_backup=false)} {--skip-maintenance : بدون maintenance mode}';

    protected string $description = 'ارتقای نسخهٔ فریمورک/ماژول‌ها (backup + patch + rollback خودکار)';

    public function handle(): int
    {
        $to = $this->option('to');
        if (!\is_string($to) || $to === '') {
            $this->error('  ✗ نسخهٔ هدف با --to= الزامی است (مثال: tvn upgrade --to=1.2.0).');

            return self::FAILURE;
        }

        /** @var Upgrader $upgrader */
        $upgrader = $this->app->make(Upgrader::class);
        $output = $this->output;
        $upgrader->setOutput(static function (string $line) use ($output): void {
            $output->writeln('  ' . $line);
        });

        try {
            $version = $upgrader->upgrade($to, [
                'no_backup' => (bool) $this->option('no-backup'),
                'skip_maintenance' => (bool) $this->option('skip-maintenance'),
            ]);
        } catch (UpgradeException $e) {
            $this->error('  ✗ ارتقا ناموفق: ' . $e->messageId);
            $this->output->writeln('    ' . ($e->hint ?? ''), 'comment');
            $this->output->writeln('    ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE), 'dim');

            return self::FAILURE;
        }

        $this->info('  ✓ سیستم به نسخهٔ ' . $version . ' ارتقا یافت.');

        return self::SUCCESS;
    }
}
