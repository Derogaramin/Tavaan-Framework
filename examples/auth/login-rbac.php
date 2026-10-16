<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — نمونهٔ امنیت/هویت (خروجی کلیدی M4 — DOC-120).
 *
 * زنجیرهٔ کامل: register → login با session hardening → نقش/مجوز (RBAC) →
 * Gate/Policy → JWT با pinning → OTP/2FA → Audit با زنجیرهٔ هش → verify.
 * همه روی SQLite in-memory.
 *
 * اجرا: php examples/auth/login-rbac.php
 */

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Engine\Audit\AuditEvent;
use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Auth\GenericUser;
use Tavaan\Engine\Auth\Jwt\Base32;
use Tavaan\Engine\Auth\TwoFactor\Totp;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Rbac\Concerns\HasRoles;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Engine\Session\Handlers\ArrayHandler;
use Tavaan\Engine\Session\Session;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();

$config = $app->make('config');
$config->set('database.default', 'sqlite_memory');
$config->set('cache.default', 'array');
$config->set('audit.key', 'demo-audit-key-32bytes-long-xxxx');
$config->set('auth.providers.users', ['driver' => 'database', 'table' => 'users', 'model' => null]);

// ─── ۱. Schema (migrationهای موتورها به‌صورت دستی برای دمو) ────────────────

$schema = $app->make('schema');

$schema->create('users', function (Blueprint $t): void {
    $t->id();
    $t->string('name');
    $t->string('email', 190)->unique();
    $t->string('password');
    $t->string('mobile', 20)->nullable();
    $t->boolean('is_banned')->default(false);
    $t->rememberToken();
    $t->timestamps(3);
});

$rbac = require \dirname(__DIR__, 2) . '/engines/rbac/migrations/2026_01_03_000001_create_rbac_tables.php';
$rbac->up();
$audit = require \dirname(__DIR__, 2) . '/engines/audit/migrations/2026_01_04_000001_create_audit_logs_table.php';
$audit->up();
$auth = require \dirname(__DIR__, 2) . '/engines/auth/migrations/2026_01_02_000001_create_auth_tables.php';
$auth->up();

echo "✓ جداول آماده شدند\n";

// ─── ۲. درخواست + نشست (شبیه‌سازی وب) ──────────────────────────────────────

$session = new Session(id: \bin2hex(\random_bytes(20)), name: 'tavaan_session', handler: new ArrayHandler());
$session->start();

$request = Request::create('/dashboard', 'POST', ['email' => 'admin@shop.ir', 'password' => 'P@ssw0rd!1403'], server: [
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Demo)',
    'HTTP_ACCEPT_LANGUAGE' => 'fa-IR',
    'REMOTE_ADDR' => '203.0.113.7',
]);
$request = $request->withAttribute('session', $session);
$app->make(RequestStack::class)->push($request);

// ─── ۳. ثبت‌نام + ورود ─────────────────────────────────────────────────────

$user = Auth::register([
    'name' => 'مدیر فروشگاه',
    'email' => 'admin@shop.ir',
    'password' => 'P@ssw0rd!1403',
]);
echo '✓ ثبت‌نام — شناسه: ' . $user->getAuthIdentifier() . ' (رمز هش‌شده با Argon2id)' . "\n";

$ok = Auth::attempt(['email' => 'admin@shop.ir', 'password' => 'P@ssw0rd!1403'], remember: true);
echo '✓ ورود — ' . ($ok ? 'موفق' : 'ناموفق!!') . ' | session regenerate شد (ضد fixation)' . "\n";
echo '  کاربر جاری: ' . (string) (Auth::user()->__get('name') ?? '-') . "\n";

// ─── ۴. RBAC: نقش/سلسله‌مراتب/مجوز ─────────────────────────────────────────

/** @var RoleManager $roles */
$roles = $app->make(RoleManager::class);
$roles->create('user', 'کاربر');
$roles->create('shop-manager', 'مدیر فروشگاه', parentKey: 'user');
$roles->createPermission('shop.order.refund', 'shop', 'بازگشت وجه سفارش');
$roles->createPermission('content.edit', 'content', 'ویرایش محتوا');
$roles->grantPermissionToRole('user', 'content.edit');
$roles->grantPermissionToRole('shop-manager', 'shop.order.refund');

final class DemoUser extends GenericUser
{
    use HasRoles;
}

$demoUser = new DemoUser(['id' => $user->getAuthIdentifier(), 'name' => 'مدیر فروشگاه']);
$demoUser->assignRole('shop-manager');

echo '✓ RBAC — hasRole(shop-manager): ' . ($demoUser->hasRole('shop-manager') ? 'بله' : 'خیر') . "\n";
echo '  hasPermission(shop.order.refund): ' . ($demoUser->hasPermission('shop.order.refund') ? 'بله' : 'خیر') . "\n";
echo '  ارث از والد (content.edit): ' . ($demoUser->hasPermission('content.edit') ? 'بله' : 'خیر') . "\n";

// ─── ۵. Gate ──────────────────────────────────────────────────────────────

Gate::define('shop.order.export', fn ($u): bool => $demoUser->hasPermission('shop.order.refund'));

echo '✓ Gate — allows(shop.order.export): ' . (Gate::forUser($demoUser)->allows('shop.order.export') ? 'بله' : 'خیر') . "\n";
echo '  denies(unknown.ability): ' . (Gate::forUser($demoUser)->denies('unknown.ability') ? 'بله' : 'خیر') . "\n";

// ─── ۶. JWT با pinning + refresh rotation ─────────────────────────────────

$pair = Auth::token()->issue($demoUser, ttl: 3600, scopes: ['shop.*']);
$claims = Auth::token()->authenticate($pair->accessToken);
echo "✓ JWT — sub={$claims['sub']} jti={$pair->tokenId} (alg پین‌شده HS256)\n";

$pair2 = Auth::token()->refresh($pair->refreshToken);
echo '  Refresh rotation — family یکسان: ' . ($pair2->family === $pair->family ? 'بله' : 'خیر') . "\n";
try {
    Auth::token()->refresh($pair->refreshToken);   // 🔒 استفادهٔ مجدد
    echo "  !! reuse باید رد می‌شد\n";
} catch (\Tavaan\Engine\Auth\Exceptions\TokenInvalidException $e) {
    echo '✓ Reuse detection — ' . $e->messageId . " (کل family باطل شد)\n";
}

// ─── ۷. OTP + TOTP ────────────────────────────────────────────────────────

$secret = Base32::randomSecret();
$totp = new Totp();
$code = $totp->generate($secret);
echo '✓ TOTP — کد جاری: ' . $code . ' | verify: ' . ($totp->verify($secret, $code) ? 'بله' : 'خیر') . "\n";

// ─── ۸. Audit با زنجیرهٔ هش ───────────────────────────────────────────────

/** @var Auditor $auditor */
$auditor = $app->make(Auditor::class);
$auditor->record(new AuditEvent('login', 'User', (string) $user->getAuthIdentifier(), userId: (int) $user->getAuthIdentifier(), ip: '203.0.113.7'));
$auditor->record(AuditEvent::update('Order', '1001', ['status' => 'paid'], ['status' => 'refunded'], userId: (int) $user->getAuthIdentifier()));

$result = $auditor->verify();
echo '✓ Audit — ' . $result->checked . " رکورد در زنجیره | یکپارچگی: " . ($result->intact ? 'سالم ✓' : 'دستکاری!!') . "\n";

// دستکاری → تشخیص
$app->make('db')->statement("UPDATE tvn_audit_logs SET new_values = '{\"status\":\"hacked\"}' WHERE id = 2");
$tampered = $auditor->verify();
echo '  پس از دستکاری: ' . ($tampered->intact ? '!!' : 'تشخیص شد ✗ (' . $tampered->reason . ')') . "\n";

echo "\n🎉 جریان کامل امنیت/هویت M4 انجام شد.\n";
