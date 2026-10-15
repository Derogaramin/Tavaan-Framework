<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Compat\Compat;
use Tavaan\Engine\Console\Command;

/**
 * سلامت محیط: افزونه‌ها، fallbackها، storage و .env (AC-CMP-03).
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class DoctorCommand extends Command
{
    protected string $signature = 'doctor';

    protected string $description = 'بررسی سلامت محیط اجرا (افزونه‌ها، fallback، مجوزها)';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
        $problems = 0;

        $this->newLine();
        $this->output->writeln('  🩺 tvn doctor', 'bold');
        $this->newLine();

        // PHP version
        $phpOk = PHP_VERSION_ID >= 80200;
        $this->line(\sprintf('  %s PHP %s (نیاز: >= 8.2)', $phpOk ? '✅' : '❌', PHP_VERSION));
        $problems += $phpOk ? 0 : 1;

        // افزونه‌ها
        $rows = [];
        foreach (Compat::report() as $ext => $info) {
            $status = $info['loaded'] ? '✅ loaded' : ($info['required'] ? '❌ missing' : '⚠️ fallback → ' . $info['fallback']);
            if (!$info['loaded'] && $info['required']) {
                $problems++;
            }
            $rows[] = [$ext, $status];
        }
        $this->table(['Extension', 'Status'], $rows);

        // storage writable
        foreach (['storage/logs', 'storage/cache', 'storage/framework/sessions', 'storage/temp'] as $dir) {
            $writable = \is_writable($base . '/' . $dir);
            $this->line(\sprintf('  %s %s %s', $writable ? '✅' : '❌', $dir, $writable ? 'writable' : 'NOT writable'));
            $problems += $writable ? 0 : 1;
        }

        // .env
        $envExists = \is_file($base . '/.env');
        $this->line(\sprintf('  %s .env %s', $envExists ? '✅' : '⚠️', $envExists ? 'found' : 'missing — cp .env.example .env'));

        // .env داخل public (خطر امنیتی)
        if (\is_file($base . '/public/.env')) {
            $this->line('  ❌ public/.env وجود دارد! 🔒 فوراً حذف کنید.');
            $problems++;
        }

        $this->newLine();
        if ($problems === 0) {
            $this->info('  ✓ محیط سالم است.');
        } else {
            $this->error("  {$problems} مشکل یافت شد.");
        }
        $this->newLine();

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
