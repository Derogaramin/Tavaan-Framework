<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;

/**
 * `tvn security:scan` — اسکن آسیب‌پذیری (DOC-059، گام ۹ DOC-121 §8).
 *
 * 🔒 ممیزی M0–M8 (R2): وعدهٔ DOC-083 بود و پیاده‌سازی نشده بود.
 *
 * طراحی ضد false-positive:
 *  • قواعد «ساختاری» روی کدِ comment/string-stripped اجرا می‌شوند (token_get_all)
 *    → docblockها، جداول قواعد، رشته‌های گرامر SQL و config شمارا flag نمی‌کنند.
 *  • SEC-BACKTICK توکنی است (عملگر backtick واقعی، نه بک‌تیک داخل رشته).
 *  • SEC-SQLI/SEC-SECRET روی خطوط خام (stringها لازم‌اند) با skip کامنت.
 *  • allowlist SEC-SHELL از config/architecture.php (منبع واحد با arch:check).
 *
 * severity: high → exit 1؛ med/low → گزارش (بازبینی انسانی).
 *
 * @api 1.0
 * @see docs/07-quality/security-checklist.md
 */
final class SecurityScanCommand extends Command
{
    protected string $signature = 'security:scan {paths?* : دایرکتوری/فایل (پیش‌فرض: core, engines, app, modules, config, public, bin)} {--json : خروجی ماشین‌خوان} {--level=all : high = فقط یافته‌های بحرانی}';

    protected string $description = 'اسکن آسیب‌پذیری (token-based — بدون وابستگی خارجی)';

    /** allowlist پایهٔ subprocess (bin/tools + خود ابزارهای QA) @var list<string> */
    private const BASE_PROCESS_ALLOW = [
        'engines/console/src/Commands/',
        'bin/',
        'tools/',
    ];

    /** allowlist دسترسی مشروع به superglobalها @var list<string> */
    private const SUPERGLOBAL_ALLOWLIST = [
        'engines/http/src/',
        'engines/console/src/',
        'engines/config/src/',
        'core/',
        'public/',
        'bin/',
    ];

    /** استثناهای مستند SEC-SQLI (بازبینی‌شده در ممیزی M0–M8) @var list<string> */
    private const SQLI_EXEMPT = [
        // زیرکوئری correlated با bindings پارامتریک؛ alias با guard شناسه محافظت می‌شود
        'engines/database/src/ORM/ModelQueryBuilder.php',
    ];

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? TVN_BASE_PATH : \dirname(__DIR__, 4);
        $asJson = (bool) $this->option('json');
        $level = \strtolower((string) ($this->option('level') ?: 'all'));

        $argPaths = (array) ($this->argument('paths') ?? []);
        $argPaths = \array_values(\array_filter(\array_map('strval', $argPaths), static fn (string $p): bool => $p !== ''));
        if ($argPaths === []) {
            $argPaths = ['core', 'engines', 'app', 'modules', 'config', 'public', 'bin'];
        }

        // allowlist توابع shell از config/architecture.php (منبع واحد با arch:check)
        $config = $this->app->bound('config')
            ? (array) $this->app->make('config')->get('architecture', [])
            : [];
        $shellAllow = self::BASE_PROCESS_ALLOW;
        foreach (['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'pcntl_exec'] as $fn) {
            foreach ((array) ($config['forbidden_functions_allow'][$fn] ?? []) as $path) {
                $shellAllow[] = (string) $path;
            }
        }

        $findings = [];
        $scanned = 0;

        foreach ($argPaths as $argPath) {
            $full = \str_starts_with($argPath, '/') ? $argPath : $base . '/' . \ltrim($argPath, '/');
            foreach ($this->phpFiles($full) as $file) {
                $rel = $this->rel($file, $base);
                if (\str_contains($rel, '/tests/') || \str_contains($rel, '/Fixtures/') || \str_contains($rel, 'storage/')) {
                    continue;
                }
                $scanned++;
                $raw = (string) \file_get_contents($file);

                // ── ۱) قواعد ساختاری روی کد stripped ────────────────────
                $code = $this->codeOnly($raw);
                $structural = [
                    ['SEC-EVAL', 'high', '/\beval\s*\(/', 'تابع eval ممنوع مطلق (ADR-0016)'],
                    ['SEC-CREATE-FUNCTION', 'high', '/\bcreate_function\s*\(/', 'create_function حذف‌شده و ناامن'],
                    ['SEC-PUTENV', 'high', '/\bputenv\s*\(/', 'putenv ممنوع — نشت به subprocess'],
                    ['SEC-SHELL', 'high', '/(?<!->)(?<!function )(?<!::)\b(shell_exec|exec|system|passthru|popen|proc_open|pcntl_exec)\s*\(/', 'اجرای shell خارج از نقطه‌های مستند'],
                    ['SEC-UNSERIALIZE', 'high', '/\bunserialize\s*\(/', 'unserialize روی دادهٔ کاربر ممنوع (F-21)'],
                    ['SEC-RAND', 'high', '/(?<![_>a-zA-Z])\brand\s*\(|\bmt_rand\s*\(/', 'rand/mt_rand در مرز امنیتی ممنوع — random_int/random_bytes'],
                    ['SEC-EXTRACT', 'high', '/(?<!->)(?<!function )\bextract\s*\(/', 'تابع extract ممنوع (F-EXTRACT)'],
                    ['SEC-GOTO', 'high', '/\bgoto\s+[A-Za-z_]/', 'goto ممنوع'],
                    ['SEC-GLOBAL', 'high', '/\bglobal\s+\$/', 'global ممنوع'],
                    ['SEC-WEAKHASH', 'med', '/\b(md5|sha1)\s*\(/', 'md5/sha1 — فقط برای checksum غیرامنیتی مجاز'],
                    ['SEC-SUPERGLOBAL', 'low', '/\$_(GET|POST|REQUEST|COOKIE)\b/', 'دسترسی مستقیم به superglobal — از Request استفاده کنید'],
                ];
                foreach ($structural as [$id, $severity, $pattern, $why]) {
                    if ($level === 'high' && $severity !== 'high') {
                        continue;
                    }
                    if ($id === 'SEC-EXTRACT' && \str_contains($rel, 'engines/view/src/ViewFactory.php')) {
                        continue; // استثنای مستند: scope ویو با EXTR_SKIP
                    }
                    if ($id === 'SEC-SHELL' && $this->inList($rel, $shellAllow)) {
                        continue;
                    }
                    if ($id === 'SEC-SUPERGLOBAL' && $this->inList($rel, self::SUPERGLOBAL_ALLOWLIST)) {
                        continue;
                    }
                    foreach ($this->findLinesRegex($code, $pattern) as $line) {
                        $findings[] = $this->finding($id, $severity, $rel, $line, $why, $raw);
                    }
                }

                // ── ۲) عملگر backtick — توکنی (نه داخل رشته)؛ severity=high ──
                foreach ($this->backtickLines($raw) as $line) {
                    $findings[] = $this->finding('SEC-BACKTICK', 'high', $rel, $line, 'عملگر backtick = shell_exec پنهان', $raw);
                }

                // ── ۳) قواعد خط‌خام (stringها لازم است) ─────────────────
                $rawRules = [
                    ['SEC-SQLI', 'high', '/\b(whereRaw|selectRaw|havingRaw|orderByRaw)\s*\(\s*(?:[^,)]*\.\s*\$|"[^"]*\$)/', 'SQL الحاقی/درون‌یابی — از binding (؟) استفاده کنید', self::SQLI_EXEMPT],
                    ['SEC-SECRET', 'med', '/(?i)[\'"]?(?:api_?key|secret|passphrase)[\'"]?\s*(?:=>|=)\s*[\'"][A-Za-z0-9+\/=_\-\.]{16,}[\'"]/', 'secret هاردکدشده', []],
                ];
                foreach ($rawRules as [$id, $severity, $pattern, $why, $exempt]) {
                    if ($level === 'high' && $severity !== 'high') {
                        continue;
                    }
                    if ($this->inList($rel, $exempt)) {
                        continue;
                    }
                    foreach (\explode("\n", $raw) as $i => $line) {
                        $trimmed = \ltrim($line);
                        if (\str_starts_with($trimmed, '*') || \str_starts_with($trimmed, '//') || \str_starts_with($trimmed, '/*') || \str_starts_with($trimmed, '#')) {
                            continue;
                        }
                        if (\preg_match($pattern, $line) === 1) {
                            $findings[] = $this->finding($id, $severity, $rel, $i + 1, $why, $raw);
                        }
                    }
                }
            }
        }

        $highs = \array_values(\array_filter($findings, static fn (array $f): bool => $f['severity'] === 'high'));

        if ($asJson) {
            $this->line((string) \json_encode([
                'ok' => $highs === [],
                'scanned' => $scanned,
                'findings' => $findings,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $highs === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($findings === []) {
            $this->info('  ✓ security:scan — هیچ یافته‌ای نیست (' . $scanned . ' فایل اسکن شد).');

            return self::SUCCESS;
        }

        $bySeverity = ['high' => 0, 'med' => 0, 'low' => 0];
        foreach ($findings as $f) {
            $bySeverity[$f['severity']]++;
        }
        $rows = [];
        foreach (\array_slice($findings, 0, 60) as $f) {
            $rows[] = [\strtoupper($f['severity']), $f['id'], $f['file'] . ':' . $f['line'], $f['why']];
        }
        $this->table(['sev', 'id', 'محل', 'چرا'], $rows);
        $this->line('');
        $this->line('  اسکن: ' . $scanned . ' فایل — high: ' . $bySeverity['high'] . '، med: ' . $bySeverity['med'] . '، low: ' . $bySeverity['low']);

        if ($highs !== []) {
            $this->error('  ✖ ' . \count($highs) . ' یافتهٔ بحرانی — باید رفع شود.');

            return self::FAILURE;
        }

        $this->info('  ✓ security:scan — بدون یافتهٔ بحرانی (med/low بازبینی شود).');

        return self::SUCCESS;
    }

    /** @return array{id: string, severity: string, file: string, line: int, why: string, snippet: string} */
    private function finding(string $id, string $severity, string $rel, int $line, string $why, string $raw): array
    {
        $lines = \explode("\n", $raw);

        return [
            'id' => $id,
            'severity' => $severity,
            'file' => $rel,
            'line' => $line,
            'why' => $why,
            'snippet' => \substr(\trim($lines[$line - 1] ?? ''), 0, 90),
        ];
    }

    /**
     * کد بدون comment و بدون string-literal (newlineها حفظ می‌شوند).
     */
    private function codeOnly(string $raw): string
    {
        $out = '';
        foreach (@\token_get_all($raw) as $token) {
            if (\is_array($token)) {
                if (\in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
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

    /**
     * خطوط حاوی عملگر backtick واقعی (توکن '`' خارج از رشته‌ها).
     * @return list<int>
     */
    private function backtickLines(string $raw): array
    {
        $lines = [];
        $line = 1;
        foreach (@\token_get_all($raw) as $token) {
            if (\is_array($token)) {
                $line += \substr_count((string) $token[1], "\n");

                continue;
            }
            if ($token === '`') {
                $lines[] = $line;
            }
        }

        return \array_values(\array_unique($lines));
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

    /** @return \Generator<string> */
    private function phpFiles(string $path): \Generator
    {
        if (\is_file($path)) {
            if (\str_ends_with($path, '.php') && !\str_ends_with($path, '.tv.php')) {
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
                static fn (\SplFileInfo $f): bool => !\in_array($f->getFilename(), ['vendor', 'node_modules', 'benchmarks'], true),
            ),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && !\str_ends_with($file->getFilename(), '.tv.php')) {
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
