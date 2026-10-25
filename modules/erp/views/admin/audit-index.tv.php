@extends('erp::layouts.admin')

@section('title', 'ردپای Audit')

@section('content')
<div class="topline">
    <h1>🔐 ردپای Audit مالی</h1>
    <span class="spacer"></span>
    @if($healthy)
        <span class="badge green">زنجیرهٔ hash سالم ✓</span>
    @else
        <span class="badge red">زنجیرهٔ hash آسیب‌دیده!</span>
    @endif
</div>

<div class="card">
    <p class="muted" style="margin-bottom:10px">
        رویدادهای مالی (سند/تأیید/ثبت/ابطال/رسید/حقوق/بستن دوره) با hash chain موتور audit ثبت می‌شوند —
        راستی‌آزمایی کامل: <code>php bin/tvn audit:verify</code>
    </p>
    <table class="data">
        <tr><th>#</th><th>رویداد</th><th>موجودیت</th><th>شناسه</th><th>کاربر</th><th>زمان</th></tr>
        @forelse($entries as $index => $entry)
            <tr>
                <td class="num">{{ trans_number(count($entries) - $index) }}</td>
                <td><span class="badge">{{ $entry['name'] ?? '—' }}</span></td>
                <td class="muted">{{ $entry['auditable_type'] ?? '—' }}</td>
                <td class="num muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis">{{ $entry['auditable_id'] ?? '—' }}</td>
                <td class="num">{{ $entry['user_id'] ?? '—' }}</td>
                <td class="num muted">{{ $entry['created_at'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">رویدادی ثبت نشده است.</td></tr>
        @endforelse
    </table>
</div>
@endsection
