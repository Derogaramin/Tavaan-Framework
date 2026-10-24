@extends('layouts.app')

@section('title', 'سلام، تَوان!')

@section('content')
    <div class="card" style="text-align:center">
        <h1 style="font-size:32px;margin:8px 0">سلام، تَوان! 👋</h1>
        <p style="color:#94a3b8">فریمورک PHP 8.2 صفر‌وابستگی — فارسی‌اول</p>
        <p>
            نگارش <b style="color:#f59e0b">{{ TVN_VERSION }}</b>
            — فاز <b>M8 (ابزار)</b> کامل شد ✅
            <span style="color:#64748b">({{ jalali(null, 'Y/m/d') }})</span>
        </p>
        <p style="color:#94a3b8;font-size:13px">
            ۵۲/۵۲ موتور فعال • ۱۲۵۱ تست سبز • بدون یک پکیج خارجی
        </p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🆕 تازه در {{ TVN_VERSION }}</h3>
        <ul style="line-height:2">
            <li>🧪 <b>Test</b> — موتور تست بومی (بدون PHPUnit): Mock بدون eval با بازتولید کامل امضا، HttpTest، RefreshDatabase، Factory+Faker قطعی، coverage (pcov) و <code>--parallel</code></li>
            <li>🏗️ <b>Scaffold</b> — ۱۷ دستور <code>make:*</code> (model/controller/migration/module/plugin/kit/…) با stub و نوشتن idempotent</li>
            <li>🔌 <b>Plugin</b> — نصب/فعال‌سازی پلاگین با <b>امضای Ed25519</b>، اسکنر کد مخرب، sandbox و Hook با policy قابلیت‌ها</li>
            <li>🖥️ <b>Admin</b> — پیشخوان CRUD-ساز RTL با ۲۷ نوع فیلد، Gate، نمودار SVG بدون JS و theme override</li>
            <li>⬆️ <b>Upgrade</b> — ارتقای گام‌به‌گام نسخه با backup اجباری، data patch، config migration و rollback خودکار</li>
            <li>🛡️ <b>ابزارهای QA ممیزی</b> — <code>arch:check</code> / <code>security:scan</code> / <code>lint:php</code> / <code>bench:all</code> / <code>env:check</code> / <code>engine:validate</code> / <code>down|up</code></li>
        </ul>
        <p style="color:#64748b;font-size:12px">جزئیات: <code>CHANGELOG.md</code> و <code>docs/reports/M8-implementation-report.md</code> + <code>docs/reports/M0-M8-audit-report.md</code></p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🧩 قابلیت‌های فعال (M1–M8)</h3>
        <ul style="line-height:2">
            <li>Container با Auto-Wiring، Contextual Binding و Union/DNF قطعی</li>
            <li>Router با <b>Compiled Trie</b> — <code>route('hello', ['name' => '…'])</code></li>
            <li>Session امن (HttpOnly/SameSite/Fingerprint/Regeneration) + CSRF-ready</li>
            <li>View Engine با <b>auto-escape</b> (<code>&#123;&#123; x &#125;&#125;</code> همیشه escape)</li>
            <li>تقویم جلالی ۱۰۰٪ دقیق (تطبیق با intl)</li>
            <li>داده: Query Builder سه‌درایوره + ORM + Migration + Validation با قواعد ایرانی</li>
            <li>امنیت: Crypt/Hash/Auth (JWT/OTP/2FA)/RBAC/Audit با hash chain</li>
            <li>زیرساخت: Queue/Schedule/Console/Filesystem/Media/Mail/Notification/Backup</li>
            <li>سرویس‌ها: API (OpenAPI 3.1)/Observability/Search فارسی/Tenancy/FeatureFlag/Realtime</li>
            <li>دامنه: Workflow (BPM)/Report (xlsx خودنوشته)/Payment (۱۰ درگاه)</li>
            <li>ابزار: Test/Scaffold/Plugin امضاشده/Admin/Upgrade + گیت‌های arch/security/lint/bench</li>
        </ul>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🔗 امتحان کنید</h3>
        <p>🆕 <a href="{{ route('demo.report') }}"><code>/demo/report</code></a> — گزارش زندهٔ HTML (فیلتر <code>?status=paid</code> + <a href="{{ route('demo.report.csv') }}">خروجی CSV</a>)</p>
        <p>🆕 <a href="{{ route('demo.workflow') }}"><code>/demo/workflow</code></a> — چرخهٔ کامل BPM + نمودار Mermaid</p>
        <p>🆕 <a href="{{ route('demo.payment') }}"><code>/demo/payment</code></a> — پرداخت کیف پول داخلی (بدون شبکه)</p>
        <p><a href="{{ route('hello', ['name' => 'دنیای-من']) }}"><code>/hello/دنیای-من</code></a> — روت پارامتریک + ویو</p>
        <p><a href="{{ route('time') }}"><code>/time</code></a> — JSON با تاریخ جلالی</p>
        <p><a href="{{ route('demo.counter') }}"><code>/demo/counter</code></a> — Session (شمارندهٔ بازدید)</p>
        <p><a href="/xss-test"><code>/xss-test</code></a> — اثبات auto-escape (۰ XSS)</p>
        <p><a href="/no-such-page"><code>/no-such-page</code></a> — صفحهٔ ۴۰۴</p>
    </div>
@endsection
