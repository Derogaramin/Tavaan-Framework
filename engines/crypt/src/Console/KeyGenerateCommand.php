<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Crypt\KeyRing;

/**
 * تولید APP_KEY و نوشتن در .env (DOC-054 §6).
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class KeyGenerateCommand extends Command
{
    protected string $signature = 'key:generate {--show : فقط نمایش، بدون نوشتن} {--force : بدون تأیید}';

    protected string $description = 'تولید کلید اپلیکیشن (APP_KEY) — AES-256';

    public function handle(): int
    {
        $key = KeyRing::generate();

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $envFile = (string) (\defined('TVN_BASE_PATH') ? TVN_BASE_PATH : '') . '/.env';
        if (!\is_file($envFile)) {
            $this->warn('  فایل .env یافت نشد — کلید روی stdout نمایش داده می‌شود:');
            $this->line('  TVN_APP_KEY=' . $key);

            return self::FAILURE;
        }

        $contents = (string) \file_get_contents($envFile);

        // فقط در صورت وجود کلید قبلیِ غیرخالی تأییدیهٔ بازنویسی پرسیده می‌شود؛
        // در راه‌اندازی اولیه (کلید خالی) و حالت غیرتعاملی جریان نباید بشکند.
        $hasExistingKey = \preg_match('/^TVN_APP_KEY=(\S.*)$/m', $contents) === 1;
        if ($hasExistingKey && !$this->option('force') && !$this->confirm('  APP_KEY بازنویسی شود؟ (دادهٔ رمزنگاری‌شدهٔ قدیمی بدون کلید قبلی خوانده نمی‌شود)')) {
            $this->warn('  لغو شد.');

            return self::SUCCESS;
        }
        if (\preg_match('/^TVN_APP_KEY=.*$/m', $contents) === 1) {
            $contents = (string) \preg_replace('/^TVN_APP_KEY=.*$/m', 'TVN_APP_KEY=' . $key, $contents);
        } else {
            $contents .= "\nTVN_APP_KEY=" . $key . "\n";
        }
        \file_put_contents($envFile, $contents, LOCK_EX);

        $this->info('  ✓ APP_KEY generated and set in .env');
        $this->comment('  ⚠️ همهٔ sessionها/کوکی‌های رمزنگاری‌شده باطل شدند.');

        return self::SUCCESS;
    }
}
