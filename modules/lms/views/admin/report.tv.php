@extends('lms::layouts.admin')

@section('title', 'گزارش پیشرفت')

@section('content')
<div class="topline">
    <h1>📊 گزارش پیشرفت</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/lms/reports/progress.csv">⬇ CSV</a>
</div>

<div class="card">
    <form method="get" action="/admin/lms/reports/progress" style="display:flex;gap:8px;align-items:end">
        <label style="font-size:.85rem;flex:1;max-width:340px">جزئیات یک دوره
            <select name="course_id">
                <option value="">— انتخاب —</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id() }}" {{ $detail !== null && $detail['course']->id() === $c->id() ? 'selected' : '' }}>{{ $c->title() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn ghost" type="submit">نمایش</button>
    </form>
</div>

@if($detail !== null)
    <div class="stats">
        <div class="stat"><div class="num">{{ trans_number($detail['enrollments']) }}</div><div class="muted">کل ثبت‌نام</div></div>
        <div class="stat"><div class="num">{{ trans_number($detail['active']) }}</div><div class="muted">در حال گذراندن</div></div>
        <div class="stat"><div class="num">{{ trans_number($detail['completed']) }}</div><div class="muted">تکمیل‌شده</div></div>
        <div class="stat"><div class="num">{{ trans_number((int) $detail['avg_progress']) }}٪</div><div class="muted">میانگین پیشرفت</div></div>
        <div class="stat"><div class="num">{{ trans_number((int) $detail['completion_rate']) }}٪</div><div class="muted">نرخ تکمیل</div></div>
    </div>

    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">دانشجویان «{{ $detail['course']->title() }}»</h3>
        <table class="data">
            <thead><tr><th>دانشجو</th><th>وضعیت</th><th>پیشرفت</th><th>آزمون‌ها (قبول/کل)</th><th>ثبت‌نام</th><th>تکمیل</th></tr></thead>
            <tbody>
            @forelse($detail['students'] as $s)
                <tr>
                    <td>{{ $s['student'] }}</td>
                    <td>{{ $s['status'] }}</td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center">
                            <div class="progress-track"><div class="progress-fill" style="width: {{ (int) $s['progress'] }}%"></div></div>
                            <span class="muted">{{ trans_number((int) $s['progress']) }}٪</span>
                        </div>
                    </td>
                    <td>{{ trans_number($s['quizzes']['passed']) }}/{{ trans_number($s['quizzes']['total']) }}</td>
                    <td class="muted">{{ $s['enrolled_at'] !== null ? jalali($s['enrolled_at'], 'Y/m/d') : '—' }}</td>
                    <td class="muted">{{ $s['completed_at'] !== null ? jalali($s['completed_at'], 'Y/m/d') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">دانشجویی نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">نمای کلی دوره‌ها</h3>
    <table class="data">
        <thead><tr><th>دوره</th><th>مدرس</th><th>وضعیت</th><th>ثبت‌نام</th><th>تکمیل</th><th>میانگین پیشرفت</th><th>نرخ تکمیل</th><th>درآمد (ریال)</th></tr></thead>
        <tbody>
        @forelse($overview as $row)
            <tr>
                <td><a href="/admin/lms/reports/progress?course_id={{ $row['course']->id() }}">{{ $row['course']->title() }}</a></td>
                <td>{{ $row['instructor'] }}</td>
                <td>{{ $row['course']->status()->labelFa() }}</td>
                <td>{{ trans_number($row['enrollments']) }}</td>
                <td>{{ trans_number($row['completed']) }}</td>
                <td>{{ trans_number((int) $row['avg_progress']) }}٪</td>
                <td>{{ trans_number((int) $row['completion_rate']) }}٪</td>
                <td>{{ $row['revenue'] > 0 ? trans_number($row['revenue']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">دوره‌ای نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
