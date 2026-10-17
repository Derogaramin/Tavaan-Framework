@extends('layouts.app')

@section('title', 'سلام، تَوان!')

@section('content')
    <div class="card" style="text-align:center">
        <h1 style="font-size:32px;margin:8px 0">سلام، تَوان! 👋</h1>
        <p style="color:#94a3b8">فریمورک PHP 8.2 صفر‌وابستگی — فارسی‌اول</p>
        <p>
            نگارش <b style="color:#f59e0b">{{ TVN_VERSION }}</b>
            — فاز <b>M7 (دامنه)</b> کامل شد ✅
            <span style="color:#64748b">({{ jalali(null, 'Y/m/d') }})</span>
        </p>
        <p style="color:#94a3b8;font-size:13px">
            ۴۸/۴۸ موتور فعال • ۹۹۲ تست سبز • بدون یک پکیج خارجی
        </p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🆕 تازه در {{ TVN_VERSION }}</h3>
        <ul style="line-height:2">
            <li>🔁 <b>Workflow (BPM)</b> — State Machine با DSL و JSON، دروازهٔ انحصاری/موازی (Fork-Join با Token)، کار انسانی (claim/complete/delegate)، تایمر، زیرفرایند، تاریخچه و نمودار DOT/Mermaid</li>
            <li>📊 <b>Report</b> — گزارش‌ساز با فیلتر/گروه‌بندی/Pivot و خروجی <b>xlsx کاملاً خودنوشته</b>، CSV ضد-injection با BOM، HTML/JSON/PDF</li>
            <li>💳 <b>Payment</b> — ۱۰ درگاه (زرین‌پال، آیدی‌پی، پی‌پینگ، سامان، به‌پرداخت، پارسیان، سداد، ملی، Stripe، کیف پول) با verify امن (مبلغ محلی + idempotency + IPN امضادار)</li>
            <li>🔧 سازگاری کامل <b>ویندوز</b> (PathGuard، Backup، Tenancy، Media) — نگارش 0.7.1</li>
        </ul>
        <p style="color:#64748b;font-size:12px">جزئیات: <code>CHANGELOG.md</code> و <code>docs/reports/M7-implementation-report.md</code></p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🧩 قابلیت‌های فعال (M1–M7)</h3>
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
