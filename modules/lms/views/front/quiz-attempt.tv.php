@extends('lms::layouts.lms')

@php
@endphp

@section('pageTitle', $quiz->title())

@section('content')
<div style="max-width:720px;margin-inline:auto">
    <div class="lms-card">
        <h1>📝 {{ $quiz->title() }}</h1>
        @if($deadline !== null)
            <p class="muted" id="lms-deadline" data-deadline="{{ $deadline->format('c') }}">
                {{ trans('lms::messages.quiz_time_limit', ['n' => trans_number($quiz->timeLimit())]) }} —
                ⏳ <span id="lms-countdown"></span>
            </p>
        @endif

        <form method="post" action="/lms/attempts/{{ $attempt->id() }}/submit" class="lms-form">
            @csrf
            @foreach($questions as $i => $question)
                <div class="lms-card" style="background:#f8fafc">
                    <p><strong>{{ trans_number($i + 1) }}. {{ $question->body() }}</strong>
                        <span class="muted">({{ $question->type()->labelFa() }} — {{ trans_number($question->score()) }} نمره)</span>
                    </p>

                    @if($question->type() === \Modules\Lms\Domain\Enums\QuestionType::MultipleChoice)
                        @foreach($question->options() as $option)
                            <label style="display:block;margin:.3rem 0">
                                <input type="radio" name="answers[{{ $question->id() }}]" value="{{ $option['key'] }}"
                                    {{ ($attempt->answerOf($question->id()) ?? '') === $option['key'] ? 'checked' : '' }}>
                                {{ $option['text'] }}
                            </label>
                        @endforeach
                    @elseif($question->type() === \Modules\Lms\Domain\Enums\QuestionType::TrueFalse)
                        <label style="display:block;margin:.3rem 0">
                            <input type="radio" name="answers[{{ $question->id() }}]" value="true"
                                {{ ($attempt->answerOf($question->id()) ?? '') === 'true' ? 'checked' : '' }}>
                            {{ trans('lms::messages.true_option') }}
                        </label>
                        <label style="display:block;margin:.3rem 0">
                            <input type="radio" name="answers[{{ $question->id() }}]" value="false"
                                {{ ($attempt->answerOf($question->id()) ?? '') === 'false' ? 'checked' : '' }}>
                            {{ trans('lms::messages.false_option') }}
                        </label>
                    @elseif($question->type() === \Modules\Lms\Domain\Enums\QuestionType::ShortAnswer)
                        <input type="text" name="answers[{{ $question->id() }}]" value="{{ $attempt->answerOf($question->id()) ?? '' }}">
                    @else
                        <textarea name="answers[{{ $question->id() }}]" rows="5">{{ $attempt->answerOf($question->id()) ?? '' }}</textarea>
                    @endif
                </div>
            @endforeach

            <button class="lms-btn success" type="submit">{{ trans('lms::messages.quiz_submit') }}</button>
        </form>
    </div>
</div>

@push('head')
@verbatim
<script>
    // تایمر آزمون — پس از انقضا، فرم قفل می‌شود (سرور هم رد می‌کند)
    (function () {
        var el = document.getElementById('lms-deadline');
        if (!el) return;
        var end = new Date(el.getAttribute('data-deadline')).getTime();
        var out = document.getElementById('lms-countdown');
        var form = document.querySelector('form[action$="/submit"]');
        function tick() {
            var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            var m = Math.floor(left / 60), s = left % 60;
            if (out) out.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (left <= 0 && form) {
                var btn = form.querySelector('button[type=submit]');
                if (btn) { btn.disabled = true; btn.textContent = '⌛'; }
            }
        }
        tick();
        setInterval(tick, 1000);
    })();
</script>
@endverbatim
@endpush
@endsection
