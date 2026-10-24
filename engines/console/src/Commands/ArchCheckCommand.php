<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn arch:check` — اجرای قوانین معماری DOC-021/DOC-022 به‌صورت
 * config-driven از `config/architecture.php`:
 *   • rules[]: {from, deny[], except[]} — مرزهای namespace (ARCH-003/005/006)
 *   • forbidden_functions[] — با allowlist نقطه‌های مستند اجرای subprocess
 *   • forbidden_patterns[] — الگوهای regex ممنوع (F-02/F-20/F-21)
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DOC-083 و گام ۹ راهنمای ایجنت (DOC-121 §8).
 *
 * @api 1.0
 * @see docs/02-architecture/layered-hmvc.md
 */
final class ArchCheckCommand extends Command
{
    protected string $signature = 'arch:check {--json : خروجی ماشین‌خوان}';

    protected string $description = 'بررسی قوانین معماری (لایه‌بندی و مرزها — config/architecture.php)';

    /**
     * allowlist اجرای subprocess — نقطه‌های مستند (DOC-062/076/058/061):
     * @var list<string>
     */
    private const PROCESS_ALLOWLIST = [
        'engines/process/',
        'engines/schedule/src/ScheduleManager.php',
        'engines/console/src/Commands/TestCommand.php',
        'engines/console/src/Commands/LintPhpCommand.php',
        'engines/console/src/Commands/BenchAllCommand.php',
        'engines/console/src/Commands/SecurityScanCommand.php',
        'engines/console/src/Commands/StyleCheckCommand.php',
        'engines/http/src/Console/ServeCommand.php',
        'engines/queue/src/Console/QueueListenCommand.php',
        'engines/mail/src/Transports/SendmailTransport.php',
        'bin/',
        'tools/',
    ];

    /** نگاشت prefix نام‌فضا → الگوی دایرکتوری منبع */
    private const NAMESPACE_DIRS = [
        'Tavaan\\Core\\' => ['core'],
        'Tavaan\\Engine\\' => ['engines/*/src'],
        'Modules\\' => ['modules/*/src'],
        'App\\' => ['app'],
    ];

    public function handle(): int
    {
        $config = $this->app->bound('config')
            ? (array) $this->app->make('config')->get('architecture', [])
            : [];
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $asJson = (bool) $this->option('json');

        $violations = [];
        $scanned = 0;

        // ── قواعد namespace (from/deny/except) ──────────────────────────
        foreach ((array) ($config['rules'] ?? []) as $index => $rule) {
            $from = (string) ($rule['from'] ?? '');
            $deny = \array_map('strval', (array) ($rule['deny'] ?? []));
            $except = \array_map('strval', (array) ($rule['except'] ?? []));
            if ($from === '' || $deny === []) {
                continue;
            }

            foreach ($this->dirsFor($from, $base) as $dir) {
                foreach ($this->phpFiles($dir) as $file) {
                    $scanned++;
                    $code = $this->codeOnly($file);
                    $ownNamespace = $this->namespaceOf($code);
                    $exempt = false;
                    foreach ($except as $exceptPrefix) {
                        if ($ownNamespace !== '' && \str_starts_with($ownNamespace, \rtrim($exceptPrefix, '\\'))) {
                            $exempt = true;
                        }
                    }
                    if ($exempt) {
                        continue;
                    }

                    foreach ($deny as $denied) {
                        foreach ($this->findLines($code, $denied) as $line) {
                            $violations[] = [
                                'rule' => 'ARCH-NS-' . ($index + 1),
                                'desc' => $from . ' → ' . $denied . ' ممنوع است',
                                'file' => $this->rel($file, $base),
                                'line' => $line,
                            ];
                        }
                    }
                }
            }
        }

        // ── توابع ممنوعه (allowlist صریح از config + نقطه‌های QA خود ابزار) ──
        $fnAllow = (array) ($config['forbidden_functions_allow'] ?? []);
        foreach ((array) ($config['forbidden_functions'] ?? []) as $fn) {
            $fn = (string) $fn;
            if ($fn === '') {
                continue;
            }
            $allowForFn = \array_map('strval', (array) ($fnAllow[$fn] ?? []));
            $rx = '/(?<!->)(?<!function )(?<!::)\b' . \preg_quote($fn, '/') . '\s*\(/';
            foreach (['core', 'engines/*/src', 'app', 'modules/*/src', 'config', 'public'] as $pattern) {
                foreach ($this->expand($base, $pattern) as $dir) {
                    foreach ($this->phpFiles($dir) as $file) {
                        $rel = $this->rel($file, $base);
                        if ($this->allowed($rel) || $this->inList($rel, $allowForFn)) {
                            continue;
                        }
                        $code = $this->codeOnly($file);
                        foreach ($this->findLinesRegex($code, $rx) as $line) {
                            $violations[] = [
                                'rule' => 'ARCH-FN',
                                'desc' => 'تابع ممنوعه: ' . $fn . '()',
                                'file' => $rel,
                                'line' => $line,
                            ];
                        }
                    }
                }
            }
        }

        // ── الگوهای ممنوعه ──────────────────────────────────────────────
        foreach ((array) ($config['forbidden_patterns'] ?? []) as $pattern) {
            $pattern = (string) $pattern;
            if ($pattern === '' || @\preg_match($pattern, '') === false) {
                continue;
            }
            foreach (['core', 'engines/*/src', 'app', 'modules/*/src'] as $dirPattern) {
                foreach ($this->expand($base, $dirPattern) as $dir) {
                    foreach ($this->phpFiles($dir) as $file) {
                        $code = $this->codeOnly($file);
                        foreach ($this->findLinesRegex($code, $pattern) as $line) {
                            $violations[] = [
                                'rule' => 'ARCH-PATTERN',
                                'desc' => 'الگوی ممنوعه: ' . $pattern,
                                'file' => $this->rel($file, $base),
                                'line' => $line,
                            ];
                        }
                    }
                }
            }
        }

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $violations === [],
                'scanned' => $scanned,
                'violations' => $violations,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations === []) {
            $this->info('  ✓ arch:check — هیچ نقض معماری یافت نشد (' . $scanned . ' فایل اسکن شد).');

            return self::SUCCESS;
        }

        $this->error('  ✖ arch:check — ' . \count($violations) . ' نقض:');
        foreach (\array_slice($violations, 0, 50) as $v) {
            $this->line('    [' . $v['rule'] . '] ' . $v['file'] . ':' . $v['line'] . ' — ' . $v['desc']);
        }
        if (\count($violations) > 50) {
            $this->comment('    … و ' . (\count($violations) - 50) . ' مورد دیگر (--json)');
        }

        return self::FAILURE;
    }

    private function allowed(string $rel): bool
    {
        return $this->inList($rel, self::PROCESS_ALLOWLIST);
    }

    /** @param list<string> $list */
    private function inList(string $rel, array $list): bool
    {
        foreach ($list as $needle) {
            if ($needle !== '' && \str_contains($rel, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function dirsFor(string $from, string $base): array
    {
        $dirs = [];
        foreach (self::NAMESPACE_DIRS as $prefix => $patterns) {
            if (\str_starts_with($from, $prefix) || $from === $prefix) {
                foreach ($patterns as $pattern) {
                    $dirs = \array_merge($dirs, $this->expand($base, $pattern));
                }
            }
        }

        return $dirs;
    }

    /** @return list<string> */
    private function expand(string $base, string $pattern): array
    {
        $out = [];
        foreach ((array) (\glob($base . '/' . $pattern, GLOB_ONLYDIR) ?: []) as $dir) {
            if (\is_dir((string) $dir)) {
                $out[] = (string) $dir;
            }
        }

        return $out;
    }

    /** @return \Generator<string> */
    private function phpFiles(string $dir): \Generator
    {
        if (!\is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $f): bool => !\in_array($f->getFilename(), ['vendor', 'Fixtures', 'benchmarks'], true),
            ),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && !\str_ends_with($file->getFilename(), '.tv.php')) {
                yield $file->getPathname();
            }
        }
    }

    /**
     * کد بدون comment و بدون string-literal — فقط ارجاع‌های واقعی namespace.
     */
    private function codeOnly(string $file): string
    {
        $out = '';
        foreach (@\token_get_all((string) \file_get_contents($file)) as $token) {
            if (\is_array($token)) {
                if (\in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
                    // newlineها حفظ شوند تا شمارهٔ خط درست بماند
                    $out .= \str_repeat("\n", \substr_count((string) $token[1], "\n"));

                    continue;
                }
                $out .= $token[1];

                continue;
            }
            $out .= $token;
        }

        return $out;
    }

    private function namespaceOf(string $code): string
    {
        if (\preg_match('/\bnamespace\s+([A-Za-z0-9_\\\\]+)\s*[;{]/', $code, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /** @return list<int> */
    private function findLines(string $code, string $needle): array
    {
        $lines = [];
        foreach (\explode("\n", $code) as $i => $line) {
            if (\str_contains($line, $needle)) {
                $lines[] = $i + 1;
            }
        }

        return $lines;
    }

    /** @return list<int> */
    private function findLinesRegex(string $code, string $regex): array
    {
        $lines = [];
        foreach (\explode("\n", $code) as $i => $line) {
            if (@\preg_match($regex, $line) === 1) {
                $lines[] = $i + 1;
            }
        }

        return $lines;
    }

    private function rel(string $file, string $base): string
    {
        $normalized = \str_replace('\\', '/', $file);

        return \str_starts_with($normalized, $base) ? \substr($normalized, \strlen($base) + 1) : $normalized;
    }
}
