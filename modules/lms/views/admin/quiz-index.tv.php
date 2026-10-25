@extends('lms::layouts.admin')

@section('title', 'آزمون‌ها')

@section('content')
<div class="topline">
    <h1>📝 آزمون‌های «{{ $course->title() }}»</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/lms/courses/{{ $course->id() }}/edit">← دوره</a>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>عنوان</th><th>درس</th><th>حد نصاب</th><th>تلاش</th><th>زمان</th><th>عملیات</th></tr></thead>
        <tbody>
        @forelse($quizzes as $quiz)
            @php
                $lessonTitle = '—';
                foreach ($lessons as $l) { if ($l->quizId() === $quiz->id()) { $lessonTitle = $l->title(); break; } }
            @endphp
            <tr>
                <td><a href="/admin/lms/quizzes/{{ $quiz->id() }}/edit"><strong>{{ $quiz->title() }}</strong></a></td>
                <td>{{ $lessonTitle }}</td>
                <td>{{ trans_number($quiz->passScore()) }}٪</td>
                <td>{{ trans_number($quiz->maxAttempts()) }}</td>
                <td>{{ $quiz->timeLimit() !== null ? trans_number($quiz->timeLimit()) . ' دقیقه' : '—' }}</td>
                <td>
                    <a class="btn sm ghost" href="/admin/lms/quizzes/{{ $quiz->id() }}/edit">ویرایش</a>
                    <form class="inline" method="post" action="/admin/lms/quizzes/{{ $quiz->id() }}" onsubmit="return confirm('حذف آزمون و تلاش‌ها؟')">
                        @csrf
                        @method('DELETE')
                        <button class="btn sm danger" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">آزمونی ساخته نشده است.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">+ آزمون جدید</h3>
    <form method="post" action="/admin/lms/courses/{{ $course->id() }}/quizzes">
        @csrf
        <div class="form-grid">
            <label class="full">عنوان *<input type="text" name="title" required></label>
            <label>حد نصاب قبولی (٪)<input type="number" name="pass_score" min="0" max="100" placeholder="50"></label>
            <label>حداکثر تلاش<input type="number" name="max_attempts" min="1" placeholder="1"></label>
            <label>محدودیت زمان (دقیقه — خالی = بدون)<input type="number" name="time_limit" min="1"></label>
            <label>اتصال به درس
                <select name="lesson_id">
                    <option value="">—</option>
                    @foreach($lessons as $l)
                        @if($l->type() === \Modules\Lms\Domain\Enums\LessonType::Quiz && $l->quizId() === null)
                            <option value="">{{ $l->title() }}</option>
                        @endif
                    @endforeach
                </select>
            </label>
        </div>
        <p style="margin-top:10px"><button class="btn" type="submit">ساخت آزمون</button></p>
        <p class="muted">پس از ساخت، آزمون را به درسِ نوع «آزمون» در فرم دوره متصل کنید (فیلد شناسهٔ آزمون).</p>
    </form>
</div>
@endsection
