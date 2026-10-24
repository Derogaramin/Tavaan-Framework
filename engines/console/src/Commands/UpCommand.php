<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn up` — خروج از حالت تعمیر (قرینهٔ `tvn down`).
 *
 * 🔒 ممیزی M0–M8 (R3): وعدهٔ DOC-083 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class UpCommand extends Command
{
    protected string $signature = 'up';

    protected string $description = 'غیرفعال‌سازی حالت تعمیر';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $file = $base . '/storage/framework/down';

        if (!\is_file($file)) {
            $this->warn('  ⚠ در حالت تعمیر نیستیم.');

            return self::SUCCESS;
        }

        if (!@\unlink($file)) {
            $this->error('  ✗ حذف ' . $file . ' ناموفق بود.');

            return self::FAILURE;
        }

        $this->info('  ✓ حالت تعمیر غیرفعال شد — اپلیکیشن در دسترس است.');

        return self::SUCCESS;
    }
}
