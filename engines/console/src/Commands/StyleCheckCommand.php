<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn style:check [--fix]` — wrapper صادقانهٔ PHP-CS-Fixer (DoD §1.1).
 *
 * فریمورک در production صفر وابستگی است؛ CS-Fixer یک dev-tool است که با
 * `composer install` (require-dev) یا phar نصب می‌شود. این دستور ابزار را
 * در مسیرهای استاندارد جست‌وجو و با config موجود
 * (`.php-cs-fixer.dist.php`) اجرا می‌کند؛ در نبودِ ابزار، راهنمای نصب
 * می‌دهد و **هرگز سکوتِ موفقیت‌آمیزِ کاذب** ندارد (exit 2 = محیط ناقص).
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DOC-083/DOC-011 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/01-standards/coding-style.md
 */
final class StyleCheckCommand extends Command
{
    protected string $signature = 'style:check {--fix : اعمال اصلاحات به‌جای dry-run} {--config= : مسیر config (پیش‌فرض .php-cs-fixer.dist.php)}';

    protected string $description = 'بررسی سبک کد با PHP-CS-Fixer (dev-tool — نیازمند نصب)';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $fix = (bool) $this->option('fix');
        $config = (string) ($this->option('config') ?: $base . '/.php-cs-fixer.dist.php');

        if (!\is_file($config)) {
            $this->error('  ✗ config یافت نشد: ' . $config);

            return self::FAILURE;
        }

        $fixer = $this->findFixer($base);
        if ($fixer === null) {
            $this->warn('  ⚠ php-cs-fixer نصب نیست — style:check اجرا نشد.');
            $this->line('    نصب: composer install (require-dev) یا:');
            $this->line('      wget https://cs.symfony.com/download/php-cs-fixer-v3.phar -O tools/php-cs-fixer.phar');
            $this->line('    config قوانین DOC-011 موجود است: .php-cs-fixer.dist.php');

            return self::INVALID; // محیط ناقص — نه موفقیت، نه شکست کد
        }

        $args = [$fixer[0] ?? PHP_BINARY];
        if (isset($fixer[1])) {
            $args[] = $fixer[1];
        }
        $args[] = 'fix';
        $args[] = '--config=' . $config;
        if (!$fix) {
            $args[] = '--dry-run';
            $args[] = '--diff';
        }
        $args[] = '--using-cache=no';

        $this->comment('  ▶ ' . \implode(' ', $args));
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open($args, $descriptors, $pipes, $base);
        if (!\is_resource($process)) {
            $this->error('  ✗ اجرای php-cs-fixer ناموفق بود.');

            return self::FAILURE;
        }
        $out = (string) \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $status = \proc_close($process);

        foreach (\array_slice(\explode("\n", \trim($out)), -25) as $line) {
            $this->line('    ' . $line);
        }

        if ($status === 0) {
            $this->info('  ✓ سبک کد مطابق DOC-011 است.');

            return self::SUCCESS;
        }

        $this->error('  ✗ ' . ($fix ? 'اصلاحات اعمال شد — diff را بازبینی کنید.' : 'diff سبک کد یافت شد — `tvn style:check --fix`.'));

        return self::FAILURE;
    }

    /** @return array{0: string, 1?: string}|null [executable, script?] */
    private function findFixer(string $base): ?array
    {
        $candidates = [
            [PHP_BINARY, $base . '/vendor/bin/php-cs-fixer'],
            [PHP_BINARY, $base . '/tools/php-cs-fixer.phar'],
        ];
        foreach ($candidates as [$exe, $script]) {
            if (\is_file($script)) {
                return [$exe, $script];
            }
        }

        // جست‌وجوی PATH بدون shell (gate: shell_exec ممنوع — F-SHELL)
        if (\strtoupper(\substr(PHP_OS, 0, 3)) !== 'WIN') {
            $pathEnv = \getenv('PATH');
            $pathEnv = \is_string($pathEnv) ? $pathEnv : (string) ($_SERVER['PATH'] ?? '');
            foreach (\explode(PATH_SEPARATOR, $pathEnv) as $dir) {
                $dir = \rtrim($dir, '/');
                if ($dir === '') {
                    continue;
                }
                $full = $dir . '/php-cs-fixer';
                if (\is_file($full) && \is_executable($full)) {
                    return [$full];
                }
            }
        }

        return null;
    }
}
