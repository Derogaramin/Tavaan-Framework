<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ابزار توسعه: تولید مستند تیکت در ساختار هر موتور/ماژول/کیت.
 *
 * خروجی‌ها:
 * - engines/<x>/README.md   — اگر موجود نباشد (DoD §2: README با شروع سریع)
 * - engines/<x>/TICKET.md   — مستند تیکت(های) متعلق به آن دایرکتوری (همیشه بازتولید)
 * - core/Autoloading/TICKET.md
 * - modules/<m>/TICKET.md   — کنار README موجود
 * - kits/<k>/TICKET.md      — کنار README موجود
 *
 * منبع داده: docs/08-roadmap/agent-backlog.md (تیکت‌ها) + module.json/kit.json
 * (مانیفست‌ها) + docs/manifest.json (نگاشت DOC-ID → مسیر) + شمارش واقعی
 * فایل/LOC/تست از خودِ دایرکتوری.
 *
 * استفاده: php tools/scripts/generate-ticket-docs.php
 */

$root = dirname(__DIR__, 2);

// ── ۱) نگاشت DOC-ID → مسیر از manifest ────────────────────────────────────
$manifest = json_decode((string) file_get_contents($root . '/docs/manifest.json'), true) ?? [];
$docPaths = [];
foreach ((array) ($manifest['catalog'] ?? []) as $entry) {
    $docId = (string) ($entry['doc_id'] ?? '');
    if ($docId !== '') {
        $docPaths[$docId] = (string) ($entry['path'] ?? '');
    }
}

// ── ۲) پارس تیکت‌ها از agent-backlog.md ───────────────────────────────────
$backlog = (string) file_get_contents($root . '/docs/08-roadmap/agent-backlog.md');
$lines = explode("\n", $backlog);

$tickets = [];
$current = null;
$mode = null;   // acceptance | files | null

foreach ($lines as $line) {
    if (preg_match('/id:\s*(TVN-\d{4})/', $line, $m) === 1) {
        if ($current !== null) {
            $tickets[$current['id']] = $current;
        }
        $current = [
            'id' => $m[1], 'title' => '', 'phase' => '', 'engine' => '', 'type' => 'engine',
            'priority' => '', 'depends_on' => [], 'docs' => [], 'loc' => null,
            'status' => 'done', 'acceptance' => [], 'files' => [], 'hints' => '',
        ];
        $mode = null;
        // ادامهٔ همین خط (قالب inline `{...}`)
        $line = str_replace('{', ' ', $line);
    }
    if ($current === null) {
        continue;
    }

    if (preg_match('/^\s*##\s|^---\s*$/', $line) === 1 && !str_contains($line, 'id:')) {
        // پایان بخش تیکت‌ها
        $tickets[$current['id']] = $current;
        $current = null;
        $mode = null;
        continue;
    }

    if (preg_match('/title:\s*"([^"]*)"/', $line, $m) === 1) {
        $current['title'] = $m[1];
    }
    if (preg_match('/phase:\s*(M\d)/', $line, $m) === 1) {
        $current['phase'] = $m[1];
    }
    if (preg_match('/\bengine:\s*([^,}\n]+)/', $line, $m) === 1) {
        $engineValue = trim($m[1]);
        // شناسهٔ کامل (tavaan.engine.x) → slug
        if (str_starts_with($engineValue, 'tavaan.engine.')) {
            $engineValue = substr($engineValue, strlen('tavaan.engine.'));
        }
        $current['engine'] = $engineValue;
    }
    if (preg_match('/type:\s*(\w+)/', $line, $m) === 1) {
        $current['type'] = $m[1];
    }
    if (preg_match('/priority:\s*(P\d)/', $line, $m) === 1) {
        $current['priority'] = $m[1];
    }
    if (preg_match('/(?:estimate_loc|loc):\s*(\d+)/', $line, $m) === 1) {
        $current['loc'] = (int) $m[1];
    }
    if (preg_match('/status:\s*(\w+)/', $line, $m) === 1) {
        $current['status'] = $m[1];
    }
    if (preg_match('/depends_on:\s*\[([^\]]*)\]/', $line, $m) === 1) {
        $current['depends_on'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    }
    if (preg_match('/docs:\s*\[([^\]]*)\]/', $line, $m) === 1) {
        $current['docs'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    }
    if (preg_match('/agent_hints:\s*"([^"]*)"/', $line, $m) === 1) {
        $current['hints'] = $m[1];
    }
    if (preg_match('/^\s*acceptance:/', $line) === 1) {
        $mode = 'acceptance';
        if (preg_match('/acceptance:\s*\[([^\]]*)\]/', $line, $m) === 1) {
            $current['acceptance'] = array_values(array_filter(array_map(
                static fn (string $x): string => trim($x, " \t\"'"),
                explode(',', $m[1]),
            )));
            $mode = null;
        }
        continue;
    }
    if (preg_match('/^\s*files_to_create:/', $line) === 1) {
        $mode = 'files';
        continue;
    }
    if ($mode !== null && preg_match('/^\s*-\s*(.+?)\s*$/', $line, $m) === 1 && !str_starts_with(trim($m[1]), 'id:')) {
        $value = trim($m[1], " \t\"'");
        if ($mode === 'acceptance') {
            $current['acceptance'][] = $value;
        } else {
            $current['files'][] = $value;
        }
        continue;
    }
    if ($mode !== null && preg_match('/^\s*\w+:/', $line) === 1) {
        $mode = null;   // فیلد جدید = پایان فهرست
    }
}
if ($current !== null) {
    $tickets[$current['id']] = $current;
}

fwrite(STDERR, 'tickets parsed: ' . count($tickets) . PHP_EOL);

// ── ۳) نگاشت تیکت → دایرکتوری مقصد ────────────────────────────────────────
$kitModules = [
    'blog' => ['core-user', 'content', 'comment', 'media', 'blog'],
    'news' => ['news'],
    'shop' => ['shop'],
    'forum' => ['forum'],
    'lms' => ['lms'],
    'crm' => ['crm'],
    'erp' => ['erp'],
    'bpms' => ['bpms'],
];
$kitSlugByTicket = [
    'TVN-0091' => 'blog', 'TVN-0092' => 'news', 'TVN-0093' => 'shop', 'TVN-0094' => 'forum',
    'TVN-0095' => 'lms', 'TVN-0096' => 'crm', 'TVN-0097' => 'erp', 'TVN-0098' => 'bpms',
];

/** @var array<string, list<array<string, mixed>>> $targets dir(relative) → tickets */
$targets = [];
foreach ($tickets as $ticket) {
    if (isset($kitSlugByTicket[$ticket['id']])) {
        $slug = $kitSlugByTicket[$ticket['id']];
        $targets['kits/' . $slug][] = $ticket;
        foreach ($kitModules[$slug] ?? [] as $module) {
            $targets['modules/' . $module][] = $ticket;
        }
        continue;
    }

    $engine = $ticket['engine'];
    if ($engine === 'autoloader (core)') {
        $targets['core/Autoloading'][] = $ticket;
        continue;
    }
    if ($engine === 'pagination') {
        $targets['engines/database'][] = $ticket;   // DOC-030 #23: pagination در database
        continue;
    }
    if ($engine === '' || !is_dir($root . '/engines/' . $engine)) {
        fwrite(STDERR, "WARN: no dir for {$ticket['id']} engine='{$engine}'\n");
        continue;
    }
    $targets['engines/' . $engine][] = $ticket;
}

// ── ۴) ابزارهای کمکی ──────────────────────────────────────────────────────
function countDir(string $dir, string $pattern = '/\.php$/'): array
{
    $files = 0;
    $loc = 0;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (preg_match($pattern, $file->getFilename()) === 1) {
                $files++;
                $loc += count(file($file->getPathname()) ?: []);
            }
        }
    }
    return [$files, $loc];
}

function countTestMethods(string $dir): array
{
    $methods = 0;
    $files = 0;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (str_ends_with($file->getFilename(), 'Test.php')) {
                $files++;
                $methods += preg_match_all('/function\s+test\w+\s*\(/', (string) file_get_contents($file->getPathname()));
            }
        }
    }
    return [$files, $methods];
}

function rel(string $from, string $to): string
{
    // مسیر نسبی ساده (هر دو از ریشه) — عمق from را برمی‌گردیم
    $depth = substr_count(trim($from, '/'), '/');
    return str_repeat('../', $depth + 1) . $to;
}

function docLink(string $docId, array $docPaths, string $fromDir): string
{
    $path = $docPaths[$docId] ?? null;
    if ($path === null) {
        return $docId;
    }
    return '[' . $docId . '](' . rel($fromDir, $path) . ')';
}

// ── ۵) تولید TICKET.md برای هر مقصد ───────────────────────────────────────
$generated = 0;
foreach ($targets as $dir => $dirTickets) {
    $abs = $root . '/' . $dir;
    if (!is_dir($abs)) {
        fwrite(STDERR, "WARN: target dir missing: {$dir}\n");
        continue;
    }

    $isEngine = str_starts_with($dir, 'engines/');
    $isModule = str_starts_with($dir, 'modules/');
    $isKit = str_starts_with($dir, 'kits/');

    // آمار واقعی دایرکتوری
    [$srcFiles, $srcLoc] = countDir($abs . '/src');
    if ($srcFiles === 0) {
        [$srcFiles, $srcLoc] = countDir($abs, '/\.php$/');   // core/Autoloading
    }
    [$testFiles, $testMethods] = countTestMethods($abs . '/tests');

    $manifestFile = $isKit ? $abs . '/kit.json' : $abs . '/module.json';
    $manifestData = is_file($manifestFile) ? (json_decode((string) file_get_contents($manifestFile), true) ?? []) : [];

    // نوع تیکت: کیت‌های M9 در backlog فیلد type ندارند → از عنوان استنتاج شود
    foreach ($dirTickets as &$tk) {
        if ($isKit && str_starts_with($tk['title'], 'Kit:')) {
            $tk['type'] = 'kit';
        } elseif ($isModule && str_starts_with($tk['title'], 'Kit:')) {
            $tk['type'] = 'kit (ماژول)';
        }
    }
    unset($tk);

    $out = "# تیکت‌های `{$dir}`\n\n";
    $out .= '> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php`'
        . " (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: " . date('Y-m-d') . "\n\n";

    if ($manifestData !== []) {
        $out .= '| مانیفست | مقدار |' . "\n|---|---|\n";
        $out .= '| شناسه | `' . (string) ($manifestData['id'] ?? '—') . "` |\n";
        $out .= '| نسخه | ' . (string) ($manifestData['version'] ?? '—') . " |\n";
        if (isset($manifestData['namespace'])) {
            $out .= '| namespace | `' . str_replace('\\\\', '\\', (string) $manifestData['namespace']) . "` |\n";
        }
        if ($isKit) {
            $modules = array_map(
                static fn (array $mod): string => '`' . (string) ($mod['id'] ?? '') . '`',
                (array) ($manifestData['modules'] ?? []),
            );
            $out .= '| ماژول‌ها | ' . implode('، ', $modules) . " |\n";
            $roles = (array) ($manifestData['roles'] ?? []);
            if ($roles !== []) {
                $out .= '| نقش‌ها | ' . count($roles) . " (" . implode('، ', array_map('strval', $roles)) . ") |\n";
            }
        } else {
            $out .= '| آمار واقعی | ' . $srcFiles . ' فایل / ' . number_format($srcLoc) . ' خط در src • '
                . $testFiles . ' فایل تست / ' . $testMethods . " متد تست |\n";
        }
        $out .= "\n";
    }

    foreach ($dirTickets as $ticket) {
        $statusIcon = ($ticket['status'] ?? 'done') === 'done' ? '✅ done' : $ticket['status'];
        $out .= "---\n\n## {$ticket['id']} — {$ticket['title']}\n\n";
        $out .= "| فیلد | مقدار |\n|---|---|\n";
        $out .= '| فاز | ' . $ticket['phase'] . " |\n";
        $out .= '| نوع/اولویت | ' . $ticket['type'] . ($ticket['priority'] !== '' ? ' / ' . $ticket['priority'] : '') . " |\n";
        $out .= '| وضعیت | ' . $statusIcon . " |\n";

        $docs = [];
        foreach ((array) $ticket['docs'] as $docId) {
            $docs[] = docLink((string) $docId, $docPaths, $dir);
        }
        if ($docs !== []) {
            $out .= '| سند | ' . implode(' • ', $docs) . " |\n";
        }

        $deps = [];
        foreach ((array) $ticket['depends_on'] as $dep) {
            $depTitle = isset($tickets[$dep]) ? $tickets[$dep]['title'] : '';
            $deps[] = $dep . ($depTitle !== '' ? ' (' . $depTitle . ')' : '');
        }
        if ($deps !== []) {
            $out .= '| پیش‌نیاز | ' . implode('، ', $deps) . " |\n";
        }
        if ($ticket['loc'] !== null) {
            $out .= '| برآورد LOC | ~' . number_format((int) $ticket['loc']) . " |\n";
        }
        $out .= "\n";

        if ($ticket['acceptance'] !== []) {
            $out .= "### معیارهای پذیرش\n";
            foreach ($ticket['acceptance'] as $ac) {
                $out .= '- ' . $ac . "\n";
            }
            $out .= "\n";
        }

        if ($ticket['files'] !== []) {
            $out .= "### فایل‌های کلیدی (طبق تیکت)\n";
            foreach ($ticket['files'] as $file) {
                $exists = is_file($root . '/' . $file) ? ' ✓' : '';
                $out .= '- `' . $file . '`' . $exists . "\n";
            }
            $out .= "\n";
        }

        $out .= "### شواهد پیاده‌سازی\n";
        if ($isEngine) {
            $slug = basename($dir);
            if ($testMethods > 0) {
                $out .= '- تست‌ها: `php bin/tvn test engines/' . $slug . '` (' . $testMethods . " متد)\n";
            } else {
                $out .= '- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`' . PHP_EOL;
            }
            $out .= "- اعتبارسنجی manifest: `php bin/tvn engine:validate`\n";
        } elseif ($isModule) {
            $slug = basename($dir);
            $out .= '- تست‌ها: `php bin/tvn test modules/' . $slug . '` (' . $testMethods . " متد)\n";
            $out .= "- اعتبارسنجی manifest: `php bin/tvn module:validate`\n";
            if (is_file($abs . '/README.md')) {
                $out .= '- معماری و راهنما: [README.md](README.md)' . "\n";
            }
        } elseif ($isKit) {
            $slug = basename($dir);
            $report = 'docs/reports/M9-' . $slug . '-implementation-report.md';
            $out .= '- نصب: `php bin/tvn kit:install ' . $slug . " --with-demo`\n";
            if (is_file($root . '/' . $report)) {
                $out .= '- گزارش پیاده‌سازی: [' . basename($report) . '](' . rel($dir, $report) . ")\n";
            }
            if (is_file($abs . '/README.md')) {
                $out .= '- راهنمای کیت: [README.md](README.md)' . "\n";
            }
            foreach ($kitModules[$slug] ?? [] as $module) {
                $out .= '- ماژول: `modules/' . $module . '` ([تیکت](' . rel($dir, 'modules/' . $module . '/TICKET.md') . "))\n";
            }
        } else {
            $out .= '- تست‌ها: `php bin/tvn test` (سوئیت سراسری)' . "\n";
        }
        $out .= "\n";

        if ($ticket['hints'] !== '') {
            $out .= "### راهنمای پیاده‌سازی (agent_hints)\n> " . $ticket['hints'] . "\n\n";
        }
    }

    file_put_contents($abs . '/TICKET.md', $out);
    $generated++;
}

// ── ۶) تولید README.md برای موتورهای فاقد آن (DoD §2) ─────────────────────
$readmes = 0;
foreach (glob($root . '/engines/*/module.json') ?: [] as $manifestFile) {
    $engineDir = dirname($manifestFile);
    if (is_file($engineDir . '/README.md')) {
        continue;
    }
    $data = json_decode((string) file_get_contents($manifestFile), true) ?? [];
    $slug = basename($engineDir);
    $name = (string) ($data['name'] ?? ucfirst($slug));
    $titleFa = (string) ($data['title_fa'] ?? $name);
    $namespace = str_replace('\\\\', '\\', (string) ($data['namespace'] ?? ''));
    $facadeName = (string) ($data['facade']['name'] ?? '');
    $accessor = (string) ($data['facade']['accessor'] ?? '');
    $configFile = (string) ($data['config']['file'] ?? '');
    $configKey = (string) ($data['config']['key'] ?? $slug);
    $docsPath = (string) ($data['docs'] ?? ('docs/03-engines/' . $slug . '.md'));
    $notes = (string) ($data['notes'] ?? '');
    $requires = (array) ($data['requires']['engines'] ?? []);
    $soft = (array) ($data['soft_requires']['engines'] ?? []);
    $tables = (array) ($data['tables'] ?? []);
    $commands = (array) ($data['commands'] ?? []);
    $providers = (array) ($data['providers'] ?? []);

    [$srcFiles, $srcLoc] = countDir($engineDir . '/src');
    [$testFiles, $testMethods] = countTestMethods($engineDir . '/tests');

    $ticketIds = array_map(static fn (array $t): string => $t['id'], $targets['engines/' . $slug] ?? []);

    $r = "# موتور {$titleFa} — `{$name}`\n\n";
    $r .= '> ' . ($notes !== '' ? $notes : 'موتور `' . $slug . '` فریمورک تَوان.') . "\n>\n";
    $r .= '> مستند کامل: [' . strtoupper(str_replace('.md', '', basename($docsPath))) . '](../../' . $docsPath . ')'
        . ($ticketIds !== [] ? ' • تیکت: ' . implode('، ', $ticketIds) . ' ([TICKET.md](TICKET.md))' : '') . "\n\n";

    $r .= "| کلید | مقدار |\n|---|---|\n";
    $r .= "| شناسه | `" . (string) ($data['id'] ?? 'tavaan.engine.' . $slug) . "` |\n";
    $r .= '| namespace | `' . $namespace . "` |\n";
    if ($facadeName !== '') {
        $r .= '| Facade | `Tavaan\\Core\\Facade\\Facades\\' . $facadeName . '` (accessor: `' . $accessor . "`) |\n";
    }
    if ($configFile !== '') {
        $r .= '| پیکربندی | `' . $configFile . '` → `config(\'' . $configKey . ".*')` |\n";
    }
    if ($providers !== []) {
        $r .= '| Provider | `' . implode('`, `', $providers) . "` |\n";
    }
    if ($requires !== []) {
        $r .= '| وابستگی سخت | ' . implode('، ', array_map(static fn (string $x): string => '`' . $x . '`', $requires)) . " |\n";
    }
    if ($soft !== []) {
        $r .= '| وابستگی نرم | ' . implode('، ', array_map(static fn (string $x): string => '`' . $x . '`', $soft)) . " |\n";
    }
    if ($tables !== []) {
        $r .= '| جداول | ' . implode('، ', array_map(static fn (string $x): string => '`' . $x . '`', $tables)) . " |\n";
    }
    if ($commands !== []) {
        $r .= '| فرمان‌های CLI | ' . implode('، ', array_map(static fn (string $x): string => '`tvn ' . $x . '`', $commands)) . " |\n";
    }
    $r .= "| آمار | {$srcFiles} فایل / ~" . number_format($srcLoc) . " خط در src • {$testFiles} فایل تست ({$testMethods} متد) |\n\n";

    if ($testMethods > 0) {
        $r .= "## شروع سریع\n\n```bash\n# تست‌های موتور ({$testMethods} متد)\nphp bin/tvn test engines/{$slug}\n\n# اعتبارسنجی manifest\nphp bin/tvn engine:validate\n```\n\n";
    } else {
        $r .= "## شروع سریع\n\n```bash\n# این موتور تست محلی ندارد — پوشش غیرمستقیم از راه سوئیت سراسری\n# و تست موتورهای همسایه (مثل router در engines/http/tests)\nphp bin/tvn test\n\n# اعتبارسنجی manifest\nphp bin/tvn engine:validate\n```\n\n";
    }

    if ($facadeName !== '') {
        $r .= "```php\n// پس از بوت فریمورک — دسترسی از راه Facade:\nuse Tavaan\\Core\\Facade\\Facades\\{$facadeName};\n\n";
        $r .= "// API عمومی و مثال‌ها: " . $docsPath . " (بخش public_api بلوک ماشین‌خوان)\n```\n\n";
    }

    $r .= "## ساختار\n\n```\nengines/{$slug}/\n├── module.json      # مانیفست (id/namespace/dependencies/tables/events)\n";
    if ($configFile !== '') {
        $r .= "├── config/          # پیش‌فرض‌های پیکربندی\n";
    }
    $r .= "├── src/             # پیاده‌سازی\n├── tests/           # تست‌های Unit/Feature\n";
    if (is_dir($engineDir . '/migrations')) {
        $r .= "├── migrations/      # جداول موتور\n";
    }
    if (is_dir($engineDir . '/benchmarks')) {
        $r .= "├── benchmarks/      # بنچمارک (اهداف DOC-112)\n";
    }
    $r .= "├── TICKET.md        # مستند تیکت (TVN-…)\n└── README.md\n```\n\n";

    $r .= "## منابع\n\n- مستند کامل موتور: [`{$docsPath}`](../../{$docsPath})\n";
    $r .= "- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)\n";
    $r .= "- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`\n";

    file_put_contents($engineDir . '/README.md', $r);
    $readmes++;
}

echo "TICKET.md generated: {$generated}\n";
echo "engine README.md generated: {$readmes}\n";
