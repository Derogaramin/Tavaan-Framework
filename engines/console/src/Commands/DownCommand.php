<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn down` / `tvn up` — حالت تعمیر (DOC-083 §2.9).
 *
 * فایل `storage/framework/down` (JSON: {time, message?, retry?, until?})
 * توسط MaintenanceModeMiddleware خوانده می‌شود → ۵۰۳ برای همه
 * (به‌جز secret/allowlist در config).
 *
 * 🔒 ممیزی M0–M8 (R3): وعدهٔ DOC-083 بود؛ Upgrader هم به همین فایل
 * تکیه می‌کند — اکنون CLI عمومی دارد.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class DownCommand extends Command
{
    protected string $signature = 'down {--message= : پیام نمایشی} {--retry= : ثانیه تا Retry-After} {--until= : زمان پایان (قابل strtotime)}';

    protected string $description = 'فعال‌سازی حالت تعمیر (۵۰۳)';

    public function handle(): int
    {
        $file = $this->downFile();
        if (\is_file($file)) {
            $this->warn('  ⚠ از قبل در حالت تعمیر هستیم: ' . $file);

            return self::SUCCESS;
        }

        $data = ['time' => \time()];
        $message = (string) ($this->option('message') ?: '');
        if ($message !== '') {
            $data['message'] = $message;
        }
        $retry = (string) ($this->option('retry') ?: '');
        if ($retry !== '' && \ctype_digit($retry)) {
            $data['retry'] = (int) $retry;
        }
        $until = (string) ($this->option('until') ?: '');
        if ($until !== '') {
            $ts = \strtotime($until);
            if ($ts === false) {
                $this->error('  ✗ --until قابل تفسیر نیست: ' . $until);

                return self::FAILURE;
            }
            $data['until'] = $ts;
        }

        $dir = \dirname($file);
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0o775, true);
        }
        if (@\file_put_contents($file, (string) \json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            $this->error('  ✗ نوشتن ' . $file . ' ناموفق بود.');

            return self::FAILURE;
        }

        $this->info('  ✓ حالت تعمیر فعال شد — ' . $file . ($message !== '' ? ' («' . $message . '»)' : ''));

        return self::SUCCESS;
    }

    private function downFile(): string
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);

        return $base . '/storage/framework/down';
    }
}
