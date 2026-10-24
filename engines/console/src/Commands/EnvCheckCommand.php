<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn env:check` — گزارش drift کلیدهای محیطی (DOC-032/DOC-083):
 *   • used: کلیدهایی که config/ و engines/*\/config/ با env_* می‌خوانند
 *   • missing-from-example: کلیدهای مصرفی که در .env.example مستند نشده‌اند
 *   • stale: کلیدهای .env.example که هیچ‌جا مصرف نمی‌شوند
 *   • missing-from-env: کلیدهای مصرفی که در .env فعال وجود ندارند
 *
 * `--strict` → exit 1 روی drift (برای CI).
 *
 * 🔒 ممیزی M0–M8 (R3): وعدهٔ DOC-083 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class EnvCheckCommand extends Command
{
    protected string $signature = 'env:check {--json : خروجی ماشین‌خوان} {--strict : exit 1 روی هر drift}';

    protected string $description = 'بررسی کلیدهای محیطی مصرفی در برابر .env.example و .env';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $asJson = (bool) $this->option('json');
        $strict = (bool) $this->option('strict');

        // ۱) کلیدهای مصرفی در همهٔ فایل‌های config
        $used = [];
        $configDirs = [$base . '/config'];
        foreach ((array) (\glob($base . '/engines/*/config', GLOB_ONLYDIR) ?: []) as $dir) {
            $configDirs[] = (string) $dir;
        }
        foreach ($configDirs as $dir) {
            foreach ((array) (\glob($dir . '/*.php') ?: []) as $file) {
                $body = (string) \file_get_contents((string) $file);
                if (\preg_match_all('/\benv_(?:string|bool|int|float|list)\(\s*[\'"]([A-Z0-9_]+)[\'"]/', $body, $m) !== false) {
                    foreach ($m[1] as $key) {
                        $used[(string) $key] = true;
                    }
                }
            }
        }
        // کلیدهای میکرواسکن bootstrap (پیش از config — مستقیم از $_SERVER/$_ENV)
        $bootstrap = $base . '/core/bootstrap.php';
        if (\is_file($bootstrap)) {
            $body = (string) \file_get_contents($bootstrap);
            if (\preg_match_all('/\$_(?:SERVER|ENV)\[[\'"]([A-Z0-9_]+)[\'"]\]/', $body, $m) !== false) {
                foreach ($m[1] as $key) {
                    $used[(string) $key] = true;
                }
            }
        }
        $used = \array_keys($used);
        \sort($used);

        // ۲) .env.example و .env
        $exampleKeys = $this->keysOf($base . '/.env.example');
        $envKeys = $this->keysOf($base . '/.env');

        $missingFromExample = \array_values(\array_diff($used, $exampleKeys));
        $stale = \array_values(\array_diff($exampleKeys, $used));
        $missingFromEnv = \array_values(\array_diff($used, $envKeys));

        $ok = $missingFromExample === [];

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $ok,
                'used' => $used,
                'missing_from_example' => $missingFromExample,
                'stale_in_example' => $stale,
                'missing_from_env' => $missingFromEnv,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return ($strict && !$ok) ? self::FAILURE : self::SUCCESS;
        }

        $this->info('  کلیدهای مصرفی در config: ' . \count($used));
        if ($missingFromExample !== []) {
            $this->warn('  ⚠ در .env.example مستند نشده (' . \count($missingFromExample) . '): ' . \implode(', ', \array_slice($missingFromExample, 0, 20)) . ($missingFromExample !== [] && \count($missingFromExample) > 20 ? ' …' : ''));
        }
        if ($stale !== []) {
            $this->warn('  ⚠ در .env.example ولی مصرف نمی‌شود (' . \count($stale) . '): ' . \implode(', ', \array_slice($stale, 0, 15)) . (\count($stale) > 15 ? ' …' : ''));
        }
        if ($missingFromEnv !== []) {
            $this->comment('  • در .env فعال نیستند (default مصرف می‌شود) (' . \count($missingFromEnv) . ')');
        }
        $this->info('  ' . ($ok ? '✓' : '⚠') . ' env:check — drift مثال: ' . \count($missingFromExample));

        return ($strict && !$ok) ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function keysOf(string $file): array
    {
        if (!\is_file($file)) {
            return [];
        }
        $keys = [];
        foreach (\explode("\n", (string) \file_get_contents($file)) as $line) {
            if (\preg_match('/^\s*#?\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $m) === 1) {
                $keys[] = $m[1];
            }
        }

        return $keys;
    }
}
