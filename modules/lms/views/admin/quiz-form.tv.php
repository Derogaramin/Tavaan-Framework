@extends('lms::layouts.admin')

@section('title', 'ویرایش آزمون')

@section('content')
<div class="topline">
    <h1>📝 {{ $quiz->title() }}</h1>
    <span class="spacer"></span>
    @if($course !== null)
        <a class="btn ghost" href="/admin/lms/courses/{{ $course->id() }}/quizzes">← آزمون‌ها</a>
    @endif
</div>

<div class="card">
    <form method="post" action="/admin/lms/quizzes/{{ $quiz->id() }}">
        @csrf
        <div class="form-grid">
            <label class="full">عنوان *<input type="text" name="title" value="{{ $quiz->title() }}" required></label>
            <label>حد نصاب قبولی (٪)<input type="number" name="pass_score" min="0" max="100" value="{{ $quiz->passScore() }}"></label>
            <label>حداکثر تلاش<input type="number" name="max_attempts" min="1" value="{{ $quiz->maxAttempts() }}"></label>
            <label>محدودیت زمان (دقیقه — خالی = بدون)<input type="number" name="time_limit" min="1" value="{{ $quiz->timeLimit() ?? '' }}"></label>
        </div>
        <p style="margin-top:10px"><button class="btn" type="submit">ذخیره</button></p>
    </form>
    <p class="muted" style="margin-top:6px">شناسهٔ آزمون (برای اتصال به درس): <code dir="ltr">{{ $quiz->id() }}</code></p>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:10px">سؤال‌ها ({{ trans_number(count($questions)) }})</h3>

    @foreach($questions as $question)
        <div class="section-box">
            <details>
                <summary style="cursor:pointer">
                    <strong>{{ trans_number($question->position() + 1) }}.</strong>
                    {{ mb_substr($question->body(), 0, 80) }}
                    <span class="badge blue">{{ $question->type()->labelFa() }}</span>
                    <span class="muted">({{ trans_number($question->score()) }} نمره)</span>
                </summary>
                <form method="post" action="/admin/lms/quizzes/{{ $quiz->id() }}/questions/{{ $question->id() }}" style="margin-top:8px">
                    @csrf
                    <div class="form-grid">
                        <label>نوع
                            <select name="type">
                                @foreach(\Modules\Lms\Domain\Enums\QuestionType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ $question->type() === $type ? 'selected' : '' }}>{{ $type->labelFa() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>نمره<input type="number" name="score" min="1" value="{{ $question->score() }}"></label>
                        <label class="full">متن سؤال *<textarea name="body" rows="2" required>{{ $question->body() }}</textarea></label>
                        <label class="full">گزینه‌ها (هر خط: key=متن — فقط چهارگزینه‌ای)
                            <textarea name="options" rows="3" dir="rtl">@foreach($question->options() as $opt){{ $opt['key'] }}={{ $opt['text'] }}
@endforeach</textarea>
                        </label>
                        <label class="full">پاسخ صحیح (کلید گزینه / true|false / متن مرجع — تشریحی: خالی)
                            <input type="text" name="correct_answer" value="{{ $question->correctAnswer() ?? '' }}" dir="ltr">
                        </label>
                        <label>ترتیب<input type="number" name="position" min="0" value="{{ $question->position() }}"></label>
                    </div>
                    <p style="margin-top:8px"><button class="btn sm" type="submit">ذخیرهٔ سؤال</button></p>
                </form>
                <form method="post" action="/admin/lms/quizzes/{{ $quiz->id() }}/questions/{{ $question->id() }}" onsubmit="return confirm('حذف سؤال؟')" style="margin-top:6px">
                    @csrf
                    @method('DELETE')
                    <button class="btn sm danger" type="submit">حذف سؤال</button>
                </form>
            </details>
        </div>
    @endforeach

    <details class="section-box">
        <summary class="btn sm ghost" style="cursor:pointer">+ سؤال جدید</summary>
        <form method="post" action="/admin/lms/quizzes/{{ $quiz->id() }}/questions" style="margin-top:8px">
            @csrf
            <div class="form-grid">
                <label>نوع
                    <select name="type">
                        @foreach(\Modules\Lms\Domain\Enums\QuestionType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->labelFa() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>نمره<input type="number" name="score" min="1" value="1"></label>
                <label class="full">متن سؤال *<textarea name="body" rows="2" required></textarea></label>
                <label class="full">گزینه‌ها (هر خط: key=متن)<textarea name="options" rows="3" placeholder="a=گزینهٔ اول&#10;b=گزینهٔ دوم"></textarea></label>
                <label class="full">پاسخ صحیح<input type="text" name="correct_answer" dir="ltr"></label>
            </div>
            <p style="margin-top:8px"><button class="btn sm" type="submit">افزودن سؤال</button></p>
        </form>
    </details>
</div>
@endsection
