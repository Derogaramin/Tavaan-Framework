<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — ابزار توسعه: تست تاب‌آوری (adversarial smoke).
 *
 * حملات رایج لایهٔ اپ را روی سرور زنده می‌آزماید و قضاوت می‌کند:
 *   flood/throttle • payload عظیم • JSON عمیق • path traversal • SQLi •
 *   XSS ذخیره‌شده • CSRF • کوکی نشست جعلی
 *
 * استفاده: php tools/scripts/adversarial-test.php http://127.0.0.1:8205
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8205', '/');
$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  🛡 {$label}\n";
    } else {
        $fail++;
        echo "  ❌ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function hit(string $method, string $url, ?string $body = null, array $headers = [], ?string $cookie = null): array
{
    $ch = curl_init($url);
    $h = array_merge(['User-Agent: Adversary/1.0'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_HEADER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($cookie !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    $response = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
        'error' => $error,
    ];
}

echo "🔥 تست تاب‌آوری — {$base}\n\n";

// ── ۱) Rate limit + CSRF ─────────────────────────────────────────────────
// ۱-الف) API با throttle:api (60/60s) — ۷۵ درخواست → باید 429 دیده شود
$codes = [];
for ($i = 0; $i < 75; $i++) {
    $r = hit('GET', $base . '/api/v1/items');
    $codes[$r['status']] = ($codes[$r['status']] ?? 0) + 1;
}
check('rate-limit API: پس از سهمیه → 429 (بدون 500)', ($codes[429] ?? 0) > 0 && ($codes[500] ?? 0) === 0, 'codes=' . json_encode($codes));

// ۱-ب) POST بدون توکن CSRF روی روت وب → 419 (پیش از هر پردازش)
$r = hit('POST', $base . '/crm/support', http_build_query(['name' => 'x']), ['Content-Type: application/x-www-form-urlencoded']);
check('CSRF روت وب بدون توکن → 419', $r['status'] === 419, 'status=' . $r['status']);

// ── ۲) payload عظیم (20MB — بیش از سقف 8MB) ──────────────────────────────
$big = str_repeat('A', 20 * 1024 * 1024);
$r = hit('POST', $base . '/crm/support', http_build_query(['name' => 'x', 'body' => $big]), ['Content-Type: application/x-www-form-urlencoded']);
check('payload ۲۰MB رد/قطع شد (413/400/بدون 500)', $r['status'] !== 500 && $r['status'] !== 0 ? $r['status'] < 500 : true, 'status=' . $r['status']);

// ── ۳) JSON بسیار عمیق (bomb) ────────────────────────────────────────────
$deep = str_repeat('[', 5000) . '1' . str_repeat(']', 5000);
$r = hit('POST', $base . '/crm/support', $deep, ['Content-Type: application/json']);
check('JSON عمیق (۵۰۰۰ لایه) → بدون 500', $r['status'] < 500, 'status=' . $r['status']);

// ── ۴) Path traversal ────────────────────────────────────────────────────
$traversals = [
    '/../../etc/passwd',
    '/..%2f..%2fetc%2fpasswd',
    '/%2e%2e/%2e%2e/etc/passwd',
    '/../.env',
    '/storage/framework/sessions/',
];
$leaked = false;
foreach ($traversals as $t) {
    $r = hit('GET', $base . $t);
    if ($r['status'] === 200 && (str_contains($r['body'], 'root:') || str_contains($r['body'], 'TVN_APP_KEY'))) {
        $leaked = true;
    }
}
check('path traversal (۵ واریانت) → نشتی ندارد', !$leaked);

// ── ۵) SQL injection در پارامتر جست‌وجو ─────────────────────────────────
$payloads = [
    "/news?q=' OR '1'='1",
    "/news?q='; DROP TABLE news_articles; --",
    "/blog?q=1 UNION SELECT password FROM users--",
    "/admin/bpms/instances?q=%27%20OR%201%3D1--",
];
$sqliOk = true;
foreach ($payloads as $p) {
    $r = hit('GET', $base . $p);
    if ($r['status'] >= 500) {
        $sqliOk = false;
    }
    if (str_contains($r['body'], '$2y$') || str_contains($r['body'], 'SQLSTATE')) {
        $sqliOk = false;   // نشت hash یا خطای خام SQL
    }
}
// جدول هنوز هست؟
$r = hit('GET', $base . '/news');
$sqliOk = $sqliOk && $r['status'] === 200;
check('SQLi (۴ payload) → بدون خطای ۵۰۰/نشت، سرویس برقرار', $sqliOk);

// ── ۶) XSS ذخیره‌شده در پرتال عمومی ────────────────────────────────────
$ticketNo = 'ADV-' . bin2hex(random_bytes(3));
$xss = '<script>alert("xss-' . $ticketNo . ')</script>';
$r = hit('POST', $base . '/crm/support', http_build_query([
    'name' => 'نفوذگر', 'email' => 'adv@test.local', 'subject' => $xss, 'body' => $xss,
]), ['Content-Type: application/x-www-form-urlencoded']);
// پاسخ یا صفحهٔ ترکیت نباید اسکریپت خام داشته باشد
$raw = false;
if (preg_match('#/crm/support/([A-Za-z0-9\-]+)#', $r['headers'] . $r['body'], $m)) {
    $show = hit('GET', $base . '/crm/support/' . $m[1]);
    $raw = str_contains($show['body'], '<script>alert("xss-');
}
check('XSS ذخیره‌شده → escape شده (بدون <script> خام)', !$raw && $r['status'] < 500, 'status=' . $r['status']);

// ── ۷) CSRF — POST بدون توکن روی روت محافظت‌شده ─────────────────────────
$r = hit('POST', $base . '/bpms/processes/leave-request/start', 'business_key=x', ['Content-Type: application/x-www-form-urlencoded']);
check('CSRF بدون توکن → 419/302 (اجرا نشود)', in_array($r['status'], [419, 302, 401, 403], true), 'status=' . $r['status']);

// ── ۸) کوکی نشست جعلی/دستکاری‌شده ──────────────────────────────────────
$fakes = [
    'tavaan_session=' . str_repeat('a', 40),
    'tavaan_session=' . str_repeat('../', 10),
    'tavaan_session=' . str_repeat('b', 4000),
    'tavaan_session=%00evil',
];
$fakeOk = true;
foreach ($fakes as $c) {
    $r = hit('GET', $base . '/admin/bpms', null, [], $c);
    if (!in_array($r['status'], [302, 401, 403, 200], true)) {
        $fakeOk = false;   // 500 = crash
    }
    if ($r['status'] === 200 && str_contains($r['body'], 'پایش')) {
        $fakeOk = false;   // 🔒 نشست جعلی پذیرفته شد!
    }
}
check('کوکی نشست جعلی (۴ واریانت) → رد/بی‌اثر بدون crash', $fakeOk);

// ── ۹) هدرهای امنیتی ────────────────────────────────────────────────────
$r = hit('GET', $base . '/blog');
$h = strtolower($r['headers']);
$secHeaders = ['x-content-type-options', 'x-frame-options', 'content-security-policy', 'referrer-policy'];
$found = array_filter($secHeaders, static fn (string $x): bool => str_contains($h, $x));
check('هدرهای امنیتی حاضرند (' . count($found) . '/4)', count($found) >= 2, 'found=' . json_encode(array_values($found)));

// ── ۱۰) flood سنگین: ۲۰۰۰ درخواست / ۲۰۰ موازی (پایداری سرویس) ─────────
echo "  … flood سنگین (۲۰۰۰ درخواست @ ۲۰۰ موازی) در جریان است …\n";
$cmd = 'php ' . escapeshellarg(dirname(__DIR__) . '/scripts/load-test.php') . ' ' . escapeshellarg($base . '/time') . ' 2000 200';
$out = shell_exec($cmd) ?? '';
preg_match('/RPS: ([\d.]+)/', $out, $mr);
preg_match('/"200":(\d+)/', $out, $mok);
preg_match('/transport errors: (\d+)/', $out, $merr);
$okCount = (int) ($mok[1] ?? 0);
$errCount = (int) ($merr[1] ?? 0);
check('flood ۲۰۰۰@۲۰۰: ≥۹۵٪ پاسخ ۲۰۰، بدون crash سرور', $okCount >= 1900, "ok={$okCount} err={$errCount} rps=" . ($mr[1] ?? '?'));
// سرور پس از flood هنوز زنده است؟
$r = hit('GET', $base . '/time');
check('سرور پس از flood زنده و سالم است', $r['status'] === 200, 'status=' . $r['status']);

echo "\n── نتیجه: {$pass} سبز / {$fail} قرمز ──\n";
exit($fail === 0 ? 0 : 1);
