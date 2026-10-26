@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.audit'))

@section('content')
<div class="topline">
    <h1>🔐 {{ trans('bpms::messages.audit') }}</h1>
    <div class="spacer"></div>
    <span class="badge {{ $chainHealthy ? 'green' : 'red' }}">{{ $chainHealthy ? 'زنجیره سالم ✅' : 'زنجیره آسیب‌دیده ⚠️' }}</span>
</div>

<div class="card">
    <table class="data">
        <thead>
        <tr><th>رویداد</th><th>موجودیت</th><th>کاربر</th><th>مقادیر</th><th>زمان</th></tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td><code>{{ $row['event'] ?? '' }}</code></td>
                <td><span class="muted">{{ $row['auditable_type'] ?? '' }}</span><br><code>{{ $row['auditable_id'] ?? '' }}</code></td>
                <td>{{ isset($row['user_id']) && $row['user_id'] !== null ? '#' . trans_number((int) $row['user_id']) : '—' }}</td>
                <td class="muted" style="max-width:320px;overflow:hidden;text-overflow:ellipsis">{{ is_string($row['new_values'] ?? null) ? $row['new_values'] : json_encode($row['new_values'] ?? [], JSON_UNESCAPED_UNICODE) }}</td>
                <td class="muted">{{ $row['created_at'] ?? '' }}</td>
            </tr>
        @endforeach
        @if(count($rows) === 0)
            <tr><td colspan="5" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>
    <div class="help" style="margin-top:8px">راستی‌آزمایی زنجیره: <code>php bin/tvn audit:verify</code></div>
</div>
@endsection
