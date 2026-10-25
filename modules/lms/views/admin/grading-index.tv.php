@extends('lms::layouts.admin')

@section('title', 'صف نمره‌دهی')

@section('content')
<div class="topline">
    <h1>📝 صف نمره‌دهی دستی</h1>
    <span class="spacer"></span>
    <span class="muted">{{ trans_number($total) }} تلاش در انتظار</span>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>دانشجو</th><th>آزمون</th><th>دوره</th><th>ارسال</th><th>نمرهٔ خودکار</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['student'] }}</td>
                <td>{{ $row['quiz']?->title() ?? '—' }}</td>
                <td>{{ $row['course']?->title() ?? '—' }}</td>
                <td>{{ $row['attempt']->submittedAt() !== null ? jalali($row['attempt']->submittedAt(), 'Y/m/d H:i') : '—' }}</td>
                <td>{{ trans_number($row['attempt']->score()) }}/{{ trans_number($row['attempt']->maxScore()) }}</td>
                <td><a class="btn sm" href="/admin/lms/grading/{{ $row['attempt']->id() }}">نمره‌دهی</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">تلاشی در انتظار نمره‌دهی نیست. 🎉</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $totalPages = (int) \max(1, \ceil($total / \max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)<span class="current">{{ trans_number($p) }}</span>
                @else<a href="/admin/lms/grading?page={{ $p }}">{{ trans_number($p) }}</a>@endif
            @endfor
        </div>
    @endif
</div>
@endsection
