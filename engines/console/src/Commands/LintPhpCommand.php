<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn lint:php` — `php -l` روی همهٔ فایل‌های PHP (معادل cross-platform
 * از tools/scripts/lint.sh برای ویندوز — DoD §1.1).
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DOC-083/DOC-111 بود و پیاده‌سازی نشده بود.
 *
 * @api 1.0
 * @see docs/07-quality/lint-rules.md
 */
final class LintPhpCommand extends Command
{
    protected string $signature = 'lint:php {paths?* : دایرکتوری/فایل (پیش‌فرض: core, engines, app, modules, config, routes, public, bin, tests, resources/lang, tools/scripts)} {--json : خروجی ماشین‌خوان}';

    protected string $description = 'بررسی نحوی همهٔ فایل‌های PHP (php -l)';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $asJson = (bool) $this->option('json');

        $argPaths = (array) ($this->argument('paths') ?? []);
        $argPaths = \array_values(\array_filter(\array_map('strval', $argPaths), static fn (string $p): bool => $p !== ''));
        if ($argPaths === []) {
            $argPaths = ['core', 'engines', 'app', 'modules', 'config', 'routes', 'public', 'bin', 'tests', 'resources/lang', 'tools/scripts'];
        }

        $failures = [];
        $linted = 0;

        foreach ($argPaths as $argPath) {
            $full = \str_starts_with($argPath, '/') ? $argPath : $base . '/' . \ltrim($argPath, '/');
            foreach ($this->phpFiles($full) as $file) {
                $rel = $this->rel($file, $base);
                if (\str_contains($rel, 'storage/') || \str_contains($rel, '/vendor/')) {
                    continue;
                }
                $linted++;
                $result = $this->lint($file);
                if ($result !== null) {
                    $failures[] = ['file' => $rel, 'error' => $result];
                }
            }
        }

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $failures === [],
                'linted' => $linted,
                'failures' => $failures,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $failures === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($failures !== []) {
            foreach ($failures as $f) {
                $this->error('  ✗ ' . $f['file'] . ' — ' . $f['error']);
            }
        }
        $this->info('  ' . ($failures === [] ? '✓' : '✗') . ' lint:php — ' . $linted . ' فایل، ' . \count($failures) . ' خطا.');

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    /** اجرای `php -l` بدون shell (سازگار با ویندوز) — خروجی: null یعنی سالم */
    private function lint(string $file): ?string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @\proc_open([PHP_BINARY, '-l', '-n', '-d', 'display_errors=1', $file], $descriptors, $pipes);
        if (!\is_resource($process)) {
            return 'proc_open failed';
        }
        $out = (string) \stream_get_contents($pipes[1]);
        $err = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $status = \proc_close($process);
        if ($status === 0) {
            return null;
        }

        return \trim($out . $err) !== '' ? \trim($out . $err) : 'exit ' . $status;
    }

    /** @return \Generator<string> */
    private function phpFiles(string $path): \Generator
    {
        if (\is_file($path)) {
            if (\str_ends_with($path, '.php')) {
                yield $path;
            }

            return;
        }
        if (!\is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $f): bool => !\in_array($f->getFilename(), ['vendor', 'node_modules'], true),
            ),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function rel(string $file, string $base): string
    {
        $normalized = \str_replace('\\', '/', $file);

        return \str_starts_with($normalized, $base) ? \substr($normalized, \strlen($base) + 1) : $normalized;
    }
}
