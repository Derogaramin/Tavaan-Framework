@extends('lms::layouts.admin')

@section('title', 'ثبت‌نام‌ها')

@section('content')
<div class="topline">
    <h1>🧾 ثبت‌نام‌ها</h1>
</div>

<div class="card">
    <form method="get" action="/admin/lms/enrollments" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="font-size:.85rem">دوره
            <select name="course_id">
                <option value="">همه</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id() }}">{{ $c->title() }}</option>
                @endforeach
            </select>
        </label>
        <label style="font-size:.85rem">وضعیت
            <select name="status">
                <option value="">همه</option>
                @foreach(\Modules\Lms\Domain\Enums\EnrollmentStatus::cases() as $s)
                    <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>دانشجو</th><th>دوره</th><th>وضعیت</th><th>پیشرفت</th><th>ثبت‌نام</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            @php $e = $row['enrollment']; @endphp
            <tr>
                <td>{{ $row['student'] }}</td>
                <td>{{ $row['course']?->title() ?? '—' }}</td>
                <td>
                    @if($e->isCompleted())<span class="badge green">{{ $e->status()->labelFa() }}</span>
                    @elseif($e->isActive())<span class="badge blue">{{ $e->status()->labelFa() }}</span>
                    @elseif($e->isPendingPayment())<span class="badge amber">{{ $e->status()->labelFa() }}</span>
                    @else<span class="badge red">{{ $e->status()->labelFa() }}</span>@endif
                </td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center">
                        <div class="progress-track"><div class="progress-fill" style="width: {{ (int) $e->progressPercent() }}%"></div></div>
                        <span class="muted">{{ trans_number((int) $e->progressPercent()) }}٪</span>
                    </div>
                </td>
                <td class="muted">{{ $e->enrolledAt() !== null ? jalali($e->enrolledAt(), 'Y/m/d') : '—' }}</td>
                <td>
                    @if($e->isCompleted())
                        <form class="inline" method="post" action="/admin/lms/enrollments/{{ $e->id() }}/certificate">
                            @csrf
                            <button class="btn sm success" type="submit">صدور گواهی</button>
                        </form>
                    @endif
                    @if($e->status() === \Modules\Lms\Domain\Enums\EnrollmentStatus::Dropped)
                        <form class="inline" method="post" action="/admin/lms/enrollments/{{ $e->id() }}/reactivate">
                            @csrf
                            <button class="btn sm ghost" type="submit">بازگردانی</button>
                        </form>
                    @elseif($e->status()->grantsAccess() || $e->isPendingPayment())
                        <form class="inline" method="post" action="/admin/lms/enrollments/{{ $e->id() }}/drop" onsubmit="return confirm('لغو ثبت‌نام؟')">
                            @csrf
                            <button class="btn sm danger" type="submit">لغو</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">ثبت‌نامی یافت نشد.</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $totalPages = (int) \max(1, \ceil($total / \max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)<span class="current">{{ trans_number($p) }}</span>
                @else<a href="/admin/lms/enrollments?page={{ $p }}">{{ trans_number($p) }}</a>@endif
            @endfor
        </div>
    @endif
</div>
@endsection
