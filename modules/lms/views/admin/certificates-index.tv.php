@extends('lms::layouts.admin')

@section('title', 'گواهی‌ها')

@section('content')
<div class="topline">
    <h1>🏅 گواهی‌ها</h1>
    <span class="spacer"></span>
    <span class="muted">{{ trans_number($total) }} گواهی</span>
</div>

<div class="card">
    <form method="get" action="/admin/lms/certificates" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:200px;font-size:.85rem">جست‌وجوی serial
            <input type="text" name="q" value="{{ $query }}" dir="ltr">
        </label>
        <label style="font-size:.85rem">وضعیت
            <select name="status">
                <option value="">همه</option>
                <option value="issued" {{ $statusFilter === 'issued' ? 'selected' : '' }}>معتبر</option>
                <option value="revoked" {{ $statusFilter === 'revoked' ? 'selected' : '' }}>باطل‌شده</option>
            </select>
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>serial</th><th>دانشجو</th><th>دوره</th><th>صدور</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            @php $cert = $row['certificate']; @endphp
            <tr>
                <td dir="ltr" style="font-family:monospace;font-size:.78rem">{{ $cert->serial() }}</td>
                <td>{{ $row['student'] }}</td>
                <td>{{ $row['course']?->title() ?? '—' }}</td>
                <td class="muted">{{ jalali($cert->issuedAt(), 'Y/m/d') }}</td>
                <td>
                    @if($cert->isValid())<span class="badge green">معتبر</span>@else<span class="badge red">باطل‌شده</span>@endif
                </td>
                <td>
                    <a class="btn sm ghost" href="{{ $cert->verifyUrl() }}" target="_blank">verify</a>
                    @if($cert->isValid())
                        <form class="inline" method="post" action="/admin/lms/certificates/{{ $cert->id() }}/revoke" onsubmit="return confirm('ابطال گواهی؟')">
                            @csrf
                            <button class="btn sm danger" type="submit">ابطال</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">گواهی‌ای صادر نشده است.</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $totalPages = (int) \max(1, \ceil($total / \max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)<span class="current">{{ trans_number($p) }}</span>
                @else<a href="/admin/lms/certificates?page={{ $p }}">{{ trans_number($p) }}</a>@endif
            @endfor
        </div>
    @endif
</div>
@endsection
