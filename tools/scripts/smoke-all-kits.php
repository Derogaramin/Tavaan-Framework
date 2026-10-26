<?php

declare(strict_types=1);

/*
 * Smoke همهٔ ۸ کیت — هم‌زیستی روی DB مشترک (معیار انتشار 1.0: «همهٔ ۸ کیت
 * اپلیکیشن نصب‌شدنی» — DOC-120 §6). صفحات health_check (عمومی) + داشبوردهای
 * پنل با نشست کوکی‌دار واقعی admin.
 *
 * استفاده: php tools/scripts/smoke-all-kits.php http://127.0.0.1:8124
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8124', '/');
$root = dirname(__DIR__, 2);

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ✅ {$label}\n";
    } else {
        $fail++;
        echo "  ❌ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function makeSession(string $root, string $email): ?array
{
    $db = new PDO('sqlite:' . $root . '/tavaan');
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
    if ($userId === false) {
        return null;
    }

    $sessionId = bin2hex(random_bytes(20));
    $token = bin2hex(random_bytes(20));
    $key = 'login_web_' . substr(hash('sha256', 'Tavaan\Engine\Auth\Guards\SessionGuard'), 0, 16);

    $dir = $root . '/storage/framework/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . '/sess_' . $sessionId, (string) json_encode([
        $key => (int) $userId,
        '_token' => $token,
        '_last_activity' => time(),
        '_created_at' => time(),
    ]));

    return ['id' => $sessionId, 'token' => $token, 'user_id' => (int) $userId];
}

function req(string $url, ?array $session = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: text/html', 'User-Agent: TavaanSmoke/1.0'];
    if ($session !== null) {
        $headers[] = 'Cookie: tavaan_session=' . $session['id'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body];
}

echo "🔥 Smoke هر ۸ کیت — {$base}\n\n";

$admin = makeSession($root, 'admin@blog.test');
check('نشست admin ساخته شد', $admin !== null);
if ($admin === null) {
    exit(1);
}

// ── صفحات health_check (عمومی) ─────────────────────────────────────────────
$public = [
    'blog' => '/blog',
    'news' => '/news',
    'shop' => '/shop',
    'forum' => '/forums',
    'lms' => '/lms',
    'crm' => '/crm/support',
];
foreach ($public as $kit => $path) {
    $r = req($base . $path);
    check("health_check کیت {$kit} ({$path}) → 200", $r['status'] === 200, 'status=' . $r['status']);
}

// ── داشبوردهای پنل (با نشست admin) ────────────────────────────────────────
$panels = [
    'admin' => '/admin',
    'blog' => '/admin/blog/posts',
    'news' => '/admin/news/articles',
    'shop' => '/admin/shop/orders',
    'forum' => '/admin/forum/forums',
    'lms' => '/admin/lms/courses',
    'crm' => '/admin/crm',
    'erp' => '/admin/erp',
    'bpms' => '/admin/bpms',
];
foreach ($panels as $kit => $path) {
    $r = req($base . $path, $admin);
    check("پنل {$kit} ({$path}) → 200", $r['status'] === 200, 'status=' . $r['status']);
}

// ── هم‌زیستی دادهٔ دمو روی DB مشترک ───────────────────────────────────────
$db = new PDO('sqlite:' . $root . '/tavaan');
$counts = [];
foreach ([
    'users' => 'users',
    'blog_posts' => 'blog_posts',
    'news_articles' => 'news_articles',
    'shop_products' => 'shop_products',
    'forum_threads' => 'forum_threads',
    'lms_courses' => 'lms_courses',
    'crm_contacts' => 'crm_contacts',
    'erp_accounts' => 'erp_accounts',
    'bpms_definitions' => 'bpms_process_definitions',
    'bpms_instances' => 'bpms_process_instances',
    'workflow_instances' => 'tvn_process_instances',
    'audit_logs' => 'tvn_audit_logs',
] as $label => $table) {
    try {
        $counts[$label] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable $e) {
        $counts[$label] = -1;
    }
}
check('دادهٔ دمو هر ۸ کیت روی DB مشترک موجود است', !in_array(0, array_values($counts), true) && !in_array(-1, array_values($counts), true), json_encode($counts));
echo '     ' . json_encode($counts, JSON_PRETTY_PRINT) . "\n";

// زنجیرهٔ audit با وجود همهٔ کیت‌ها سالم بماند
$out = shell_exec('cd ' . escapeshellarg($root) . ' && php bin/tvn audit:verify 2>&1');
check('audit:verify سالم (پس از نصب هر ۸ کیت)', is_string($out) && (str_contains($out, 'سالم') || str_contains($out, 'intact') || str_contains($out, '✓')), trim(substr((string) $out, 0, 160)));

echo "\n── نتیجه: {$pass} سبز / {$fail} قرمز ──\n";
exit($fail === 0 ? 0 : 1);
