@extends('layouts.app')

@section('title', 'سلام، تَوان!')

@section('content')
    <div class="card" style="text-align:center">
        <h1 style="font-size:32px;margin:8px 0">سلام، تَوان! 👋</h1>
        <p style="color:#94a3b8">فریمورک PHP 8.2 صفر‌وابستگی — فارسی‌اول</p>
        <p>امروز: <b>{{ jalali(null, 'Y/m/d l') }}</b> — <code>{{ $bootMs ?? '—' }} ms</code> boot</p>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🧩 قابلیت‌های فعال (M1 + M2)</h3>
        <ul style="line-height:2">
            <li>Container با Auto-Wiring، Contextual Binding و Union/DNF قطعی</li>
            <li>Router با <b>Compiled Trie</b> — <code>route('hello', ['name' => '…'])</code></li>
            <li>Session امن (HttpOnly/SameSite/Fingerprint/Regeneration) + CSRF-ready</li>
            <li>View Engine با <b>auto-escape</b> (<code>&#123;&#123; x &#125;&#125;</code> همیشه escape)</li>
            <li>تقویم جلالی ۱۰۰٪ دقیق (تطبیق با intl)</li>
            <li>کوکی رمزنگاری‌شده (AES-256-GCM) + CORS + Security Headers</li>
        </ul>
    </div>

    <div class="card">
        <h3 style="margin-top:0">🔗 امتحان کنید</h3>
        <p><a href="{{ route('hello', ['name' => 'دنیای-من']) }}"><code>/hello/دنیای-من</code></a> — روت پارامتریک + ویو</p>
        <p><a href="{{ route('time') }}"><code>/time</code></a> — JSON با تاریخ جلالی</p>
        <p><a href="{{ route('demo.counter') }}"><code>/demo/counter</code></a> — Session (شمارندهٔ بازدید)</p>
        <p><a href="/xss-test"><code>/xss-test</code></a> — اثبات auto-escape (۰ XSS)</p>
        <p><a href="/no-such-page"><code>/no-such-page</code></a> — صفحهٔ ۴۰۴</p>
    </div>
@endsection
