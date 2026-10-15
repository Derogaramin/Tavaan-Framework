@extends('layouts.app')

@section('title', 'تست Auto-Escape')

@section('content')
    <div class="card">
        <h3>تست XSS — ورودی: <code>&lt;script&gt;alert(1)&lt;/script&gt;</code></h3>
        <p>خروجی escape شده با <code>&#123;&#123; $input &#125;&#125;</code>:</p>
        <div style="background:#0b1120;padding:12px;border-radius:8px;direction:ltr">{{ $input }}</div>
        <p style="color:#22c55e">✓ اگر متن بالا را به‌صورت کد می‌بینید (نه اجرای alert) یعنی auto-escape کار می‌کند.</p>
    </div>
@endsection
