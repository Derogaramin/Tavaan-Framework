<?php

declare(strict_types=1);

/*
 * Smoke واقعی HTTP برای کیت BPMS — نشست کوکی‌دار روی php -S.
 * (الگوی گزارش M9 گام ۷ — احراز هویت نشست‌محور واقعی، نه actingAs درون‌حافظه‌ای)
 *
 * استفاده: php tools/scripts/smoke-bpms.php http://127.0.0.1:8123
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
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

// ── نشست واقعی: ساخت فایل session برای یک کاربر ────────────────────────────
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
    // FileHandler فایل را با prefix «sess_» ذخیره می‌کند
    file_put_contents($dir . '/sess_' . $sessionId, (string) json_encode([
        $key => (int) $userId,
        '_token' => $token,
        '_last_activity' => time(),
        '_created_at' => time(),
    ]));

    return ['id' => $sessionId, 'token' => $token, 'user_id' => (int) $userId];
}

function req(string $method, string $url, ?array $session = null, array $post = [], bool $follow = false): array
{
    $ch = curl_init($url);
    $headers = ['Accept: text/html,application/xhtml+xml', 'User-Agent: TavaanSmoke/1.0'];
    if ($session !== null) {
        $headers[] = 'Cookie: tavaan_session=' . $session['id'];
        $headers[] = 'X-CSRF-TOKEN: ' . $session['token'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($post !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $redirect = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?? '');
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'type' => $contentType, 'redirect' => $redirect];
}

echo "🔥 BPMS HTTP smoke — {$base}\n\n";

$admin = makeSession($root, 'admin@blog.test');
$reader = makeSession($root, 'reader@blog.test');
check('ساخت نشست admin', $admin !== null);
check('ساخت نشست reader (auditor/monitor)', $reader !== null);
if ($admin === null || $reader === null) {
    exit(1);
}

// ── صفحات پنل ──────────────────────────────────────────────────────────────
$r = req('GET', $base . '/admin/bpms', $admin);
check('GET /admin/bpms → 200 (داشبورد پایش)', $r['status'] === 200 && str_contains($r['body'], 'پایش'), 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/definitions', $admin);
check('GET /admin/bpms/definitions → 200 + leave-request', $r['status'] === 200 && str_contains($r['body'], 'leave-request'), 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/instances', $admin);
check('GET /admin/bpms/instances → 200 + DEMO-LEAVE-MANAGER', $r['status'] === 200 && str_contains($r['body'], 'DEMO-LEAVE-MANAGER'), 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/tasks', $admin);
check('GET /admin/bpms/tasks → 200', $r['status'] === 200, 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/audit', $admin);
check('GET /admin/bpms/audit → 200 + زنجیره سالم', $r['status'] === 200 && str_contains($r['body'], 'زنجیره سالم'), 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/reports/kpis.csv', $admin);
check('GET kpis.csv → 200 + text/csv + BOM', $r['status'] === 200 && str_contains($r['type'], 'text/csv') && str_starts_with($r['body'], "\xEF\xBB\xBF"), 'status=' . $r['status']);

// تعریف + نمودار SVG
$db = new PDO('sqlite:' . $root . '/tavaan');
$defId = (string) $db->query("SELECT id FROM bpms_process_definitions WHERE key='leave-request' AND is_active=1")->fetchColumn();
$r = req('GET', $base . '/admin/bpms/definitions/' . $defId, $admin);
check('GET definition show → 200 + <svg', $r['status'] === 200 && str_contains($r['body'], '<svg'), 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/definitions/' . $defId . '/diagram.svg', $admin);
check('GET diagram.svg → 200 + image/svg+xml', $r['status'] === 200 && str_contains($r['type'], 'image/svg+xml') && str_starts_with($r['body'], '<svg'), 'status=' . $r['status']);

// ── صفحات وب (کاربر) ───────────────────────────────────────────────────────
$r = req('GET', $base . '/bpms/tasks', $admin);
check('GET /bpms/tasks → 200 (inbox)', $r['status'] === 200, 'status=' . $r['status']);

$r = req('GET', $base . '/bpms/processes', $admin);
check('GET /bpms/processes → 200 + کاتالوگ', $r['status'] === 200 && str_contains($r['body'], 'درخواست مرخصی'), 'status=' . $r['status']);

$r = req('GET', $base . '/bpms/processes/leave-request/start', $admin);
check('GET start page → 200 + نمودار', $r['status'] === 200 && str_contains($r['body'], '<svg'), 'status=' . $r['status']);

// مهمان → redirect
$r = req('GET', $base . '/bpms/tasks');
check('GET /bpms/tasks مهمان → 302', $r['status'] === 302, 'status=' . $r['status']);

// ── ماتریس نقش: reader (auditor+monitor) ───────────────────────────────────
$r = req('GET', $base . '/admin/bpms', $reader);
check('reader: GET /admin/bpms → 200 (monitor.view)', $r['status'] === 200, 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/audit', $reader);
check('reader: GET /admin/bpms/audit → 200 (audit.view)', $r['status'] === 200, 'status=' . $r['status']);

$instId = (string) $db->query("SELECT id FROM bpms_process_instances WHERE business_key='DEMO-LEAVE-MANAGER'")->fetchColumn();
$r = req('POST', $base . '/admin/bpms/instances/' . $instId . '/terminate', $reader, ['reason' => 'x']);
check('reader: POST terminate → 403', $r['status'] === 403, 'status=' . $r['status']);

$r = req('GET', $base . '/admin/bpms/definitions/create', $reader);
check('reader: GET definitions/create → 403', $r['status'] === 403, 'status=' . $r['status']);

// ── جریان زندهٔ E2E: start → submit → auto-approve (HTTP واقعی) ────────────
$r = req('POST', $base . '/bpms/processes/leave-request/start', $admin, ['business_key' => 'SMOKE-1'], follow: false);
check('POST start → 302 به نمونه', $r['status'] === 302 && str_contains($r['redirect'], '/bpms/instances/'), 'status=' . $r['status'] . ' loc=' . $r['redirect']);

preg_match('#/bpms/instances/([0-9A-Z]{26})#', $r['redirect'], $m);
$smokeInstanceId = $m[1] ?? '';
check('شناسهٔ نمونه استخراج شد', $smokeInstanceId !== '');

$taskRow = $db->query("SELECT id, state_id, form_id FROM bpms_tasks WHERE instance_id='{$smokeInstanceId}' AND status='pending'")->fetch(PDO::FETCH_ASSOC);
check('task «submit» برای initiator ساخته شد', is_array($taskRow) && $taskRow['state_id'] === 'submit');

if (is_array($taskRow)) {
    $taskPage = req('GET', $base . '/bpms/tasks/' . $taskRow['id'], $admin);
    check('صفحهٔ task → 200 + فرم پویا (leave_type/days)', $taskPage['status'] === 200
        && str_contains($taskPage['body'], 'name="leave_type"') && str_contains($taskPage['body'], 'name="days"'), 'status=' . $taskPage['status']);

    // دادهٔ نامعتبر → redirect با خطا (days=99 > max)
    $bad = req('POST', $base . '/bpms/tasks/' . $taskRow['id'] . '/complete', $admin, [
        'leave_type' => 'استحقاقی', 'days' => '99', 'from_date' => '1405/08/10', 'to_date' => '1405/08/11',
    ]);
    $stillPending = $db->query("SELECT COUNT(*) FROM bpms_tasks WHERE id='{$taskRow['id']}' AND status='pending'")->fetchColumn();
    check('فرم نامعتبر رد شد (task باز ماند)', $bad['status'] === 302 && (int) $stillPending === 1, 'status=' . $bad['status']);

    // دادهٔ معتبر (days=1 → auto-approve)
    $ok = req('POST', $base . '/bpms/tasks/' . $taskRow['id'] . '/complete', $admin, [
        'leave_type' => 'استحقاقی', 'days' => '1', 'from_date' => '1405/08/10', 'to_date' => '1405/08/10', 'reason' => 'smoke',
    ]);
    check('POST complete → 302', $ok['status'] === 302, 'status=' . $ok['status']);

    $final = $db->query("SELECT status, current_state, workflow_instance_id FROM bpms_process_instances WHERE id='{$smokeInstanceId}'")->fetch(PDO::FETCH_ASSOC);
    check('نمونه completed شد (auto-approve)', ($final['status'] ?? '') === 'completed', json_encode($final, JSON_UNESCAPED_UNICODE));

    $mirror = $db->query("SELECT status FROM tvn_process_instances WHERE id='{$final['workflow_instance_id']}'")->fetch(PDO::FETCH_ASSOC);
    check('mirror موتور workflow هم completed', ($mirror['status'] ?? '') === 'completed', json_encode($mirror));

    $hist = (int) $db->query("SELECT COUNT(*) FROM bpms_history WHERE instance_id='{$smokeInstanceId}'")->fetchColumn();
    check('history کامل ثبت شد (≥6 ردیف)', $hist >= 6, "count={$hist}");
}

// ── timer CLI واقعی ────────────────────────────────────────────────────────
$timerOut = shell_exec('cd ' . escapeshellarg($root) . ' && php bin/tvn bpms:check-timers 2>&1');
check('tvn bpms:check-timers بدون خطا اجرا شد', is_string($timerOut) && !str_contains($timerOut, 'Exception'), substr((string) $timerOut, 0, 120));

echo "\n── نتیجه: {$pass} سبز / {$fail} قرمز ──\n";
exit($fail === 0 ? 0 : 1);
