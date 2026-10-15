<?php

/**
 * تَوان | Tavaan — Quality Gate
 * بررسی موارد ممنوعهٔ DOC-010 §2.2 + AST-level checks سبک (regex).
 *
 * اجرا: php tools/scripts/check-forbidden.php [path...]
 */

declare(strict_types=1);

$base = \dirname(__DIR__, 2);
$paths = \array_slice($argv, 1) ?: [$base . '/core', $base . '/engines', $base . '/bin', $base . '/public', $base . '/config'];

/** قوانین: الگو → شناسه → استثناهای مجاز (مسیر شامل) */
$rules = [
    ['/\beval\s*\(/',                        'F-EVAL',      'eval() ممنوع مطلق (ADR-0016)', []],
    ['/\bcreate_function\s*\(/',             'F-07',        'create_function حذف‌شده', []],
    ['/\bputenv\s*\(/',                      'AC-BOOT-06',  'putenv ممنوع (E2)', []],
    ['/\bgoto\s+[A-Za-z_]/',                 'F-GOTO',      'goto ممنوع', []],
    ['/\bglobal\s+\$/',                      'F-GLOBAL',    'global ممنوع', []],
    ['/\bextract\s*\(/',                     'F-EXTRACT',   'extract ممنوع', []],
    ['/\bshell_exec\s*\(/',                  'F-SHELL',     'shell_exec ممنوع در core/engines', []],
    ['/\butf8_(en|de)code\s*\(/',            'F-03',        'utf8_encode/decode منسوخ', []],
    ['/\bstrftime\s*\(/',                    'F-05',        'strftime منسوخ', []],
    ['/\bmoney_format\s*\(/',                'F-09',        'money_format حذف‌شده', []],
    ['/\bmt_rand\s*\(/',                     'F-20',        'mt_rand برای توکن امنیتی ممنوع', []],
    ['/\bunserialize\s*\(/',                 'F-21',        'unserialize روی دادهٔ کاربر ممنوع', []],
    ['/\$\{[A-Za-z_]/',                      'F-02',        '${var} interpolation منسوخ (8.2)', ['config/architecture.php']],
    ['/\bSerializable\b/',                   'F-29',        'interface Serializable منسوخ', []],
    ['/\bReturnTypeWillChange\b/',           'F-14',        'ReturnTypeWillChange ممنوع — type صریح', []],
    ['/private const (string|int|float|bool|array) /', 'PHP83-CONST', 'typed class constants در 8.2 مجاز نیست', []],
];

$violations = [];
$scanned = 0;

$scan = function (string $path) use (&$scan, &$scanned, &$violations, $rules): void {
    if (\is_file($path)) {
        if (\substr($path, -4) !== '.php' && !\str_ends_with($path, '/tvn')) {
            return;
        }
        $scanned++;
        $contents = (string) \file_get_contents($path);
        $lines = \explode("\n", $contents);

        foreach ($rules as [$pattern, $id, $why, $exempt]) {
            foreach ($exempt as $needle) {
                if (\str_contains($path, $needle)) {
                    continue 2;
                }
            }
            foreach ($lines as $no => $line) {
                // پرش از کامنت‌های تک‌خطی و خط‌های مستند که «قانون» را نقل می‌کنند
                $trimmed = \ltrim($line);
                if (\str_starts_with($trimmed, '//') || \str_starts_with($trimmed, '*') || \str_starts_with($trimmed, '/*')) {
                    continue;
                }
                if (\preg_match($pattern, $line) === 1) {
                    // string literal داخل نقل‌قول هم نقض محسوب می‌شود مگر در لیست مجاز ابزارها
                    $violations[] = \sprintf('%s:%d [%s] %s | %s', \str_replace(\dirname(__DIR__, 2) . '/', '', $path), $no + 1, $id, $why, $trimmed);
                }
            }
        }

        return;
    }

    if (!\is_dir($path)) {
        return;
    }

    foreach (\scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || \in_array($entry, ['vendor', 'node_modules', 'tests'], true)) {
            continue;
        }
        $scan($path . '/' . $entry);
    }
};

foreach ($paths as $path) {
    $scan($path);
}

// بررسی declare(strict_types=1) در همهٔ فایل‌های کلاس
$missingStrict = [];
$checkStrict = function (string $path) use (&$checkStrict, &$missingStrict): void {
    if (\is_dir($path)) {
        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || \in_array($entry, ['vendor', 'node_modules'], true)) {
                continue;
            }
            $checkStrict($path . '/' . $entry);
        }

        return;
    }
    if (\substr($path, -4) !== '.php') {
        return;
    }
    $head = (string) \file_get_contents($path, false, null, 0, 600);
    if (!\str_contains($head, 'declare(strict_types=1)')) {
        $missingStrict[] = $path;
    }
};
foreach ([$base . '/core', $base . '/engines'] as $p) {
    $checkStrict($p);
}

echo "Scanned: {$scanned} files\n";

$ok = true;
if ($violations !== []) {
    $ok = false;
    echo "\n❌ Forbidden constructs (" . \count($violations) . "):\n";
    foreach (\array_slice($violations, 0, 40) as $v) {
        echo '  ' . $v . "\n";
    }
}
if ($missingStrict !== []) {
    $ok = false;
    echo "\n❌ Missing declare(strict_types=1) (" . \count($missingStrict) . "):\n";
    foreach (\array_slice($missingStrict, 0, 20) as $f) {
        echo '  ' . $f . "\n";
    }
}

if ($ok) {
    echo "✅ No forbidden constructs; strict_types present everywhere.\n";
    exit(0);
}
exit(1);
