<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Upgrade\Exceptions\UpgradeException;
use Tavaan\Engine\Upgrade\Upgrader;

/**
 * `tvn upgrade:rollback` — بازگشت از آخرین backup (DOC-081E §6/§7).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeRollbackCommand extends Command
{
    protected string $signature = 'upgrade:rollback {--force : بدون تأیید تعاملی}';

    protected string $description = 'بازگشت به آخرین backup ارتقا';

    public function handle(): int
    {
        /** @var Upgrader $upgrader */
        $upgrader = $this->app->make(Upgrader::class);

        if (!$this->option('force')) {
            $answer = $this->input->readLine('  ⚠ بازگشت به آخرین backup — همهٔ تغییرات پس از آن از بین می‌رود. ادامه؟ (yes/no): ');
            if (\trim((string) $answer) !== 'yes') {
                $this->info('  لغو شد.');

                return self::SUCCESS;
            }
        }

        try {
            $version = $upgrader->rollback();
        } catch (UpgradeException $e) {
            $this->error('  ✗ ' . $e->messageId . ' — ' . ($e->hint ?? ''));

            return self::FAILURE;
        }

        $this->info('  ✓ بازگشت به نسخهٔ ' . $version . ' انجام شد.');

        return self::SUCCESS;
    }
}
