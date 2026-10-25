@extends('lms::layouts.admin')

@section('title', 'نمره‌دهی تلاش')

@section('content')
<div class="topline">
    <h1>📝 نمره‌دهی — {{ $quiz->title() }}</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/lms/grading">← صف</a>
</div>

<div class="card">
    <p>
        <strong>دانشجو:</strong> {{ $student }} •
        <strong>دوره:</strong> {{ $course?->title() }} •
        <strong>نمرهٔ خودکار:</strong> {{ trans_number($attempt->score()) }}/{{ trans_number($attempt->maxScore()) }} •
        <strong>حد نصاب:</strong> {{ trans_number($quiz->passScore()) }}٪
    </p>
</div>

<form method="post" action="/admin/lms/grading/{{ $attempt->id() }}">
    @csrf
    @foreach($breakdown as $i => $row)
        @php $q = $row['question']; @endphp
        <div class="card">
            <p><strong>{{ trans_number($i + 1) }}. {{ $q->body() }}</strong>
                <span class="badge blue">{{ $q->type()->labelFa() }}</span>
                <span class="muted">({{ trans_number($q->score()) }} نمره)</span>
            </p>

            <p class="muted">پاسخ دانشجو:</p>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;margin-bottom:8px;white-space:pre-wrap">{{ $row['answer'] ?? '—' }}</div>

            @if($row['auto'] !== null)
                <p>{{ $row['auto']['correct'] ? '✅ خودکار — صحیح' : '❌ خودکار — نادرست' }}
                    ({{ trans_number($row['auto']['score']) }}/{{ trans_number($q->score()) }})</p>
            @else
                <div class="form-grid">
                    <label>نمرهٔ دستی (۰ تا {{ $q->score() }})
                        <input type="number" name="grades[{{ $q->id() }}][score]" min="0" max="{{ $q->score() }}" value="0" required>
                    </label>
                    <label>یادداشت برای دانشجو
                        <input type="text" name="grades[{{ $q->id() }}][note]">
                    </label>
                </div>
            @endif
        </div>
    @endforeach

    <p><button class="btn success" type="submit">ثبت نمره‌ها و اعلام نتیجه</button></p>
</form>
@endsection
