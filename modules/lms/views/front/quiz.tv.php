@extends('lms::layouts.lms')

@php
@endphp

@section('pageTitle', $quiz->title() . ' — ' . trans('lms::messages.quiz'))

@section('content')
<div style="max-width:640px;margin-inline:auto">
    <div class="lms-card">
        <h1>📝 {{ $quiz->title() }}</h1>
        @if($course !== null)
            <p class="muted"><a href="/lms/learn/{{ $course->slug() }}">← {{ $course->title() }}</a></p>
        @endif

        <p class="muted">
            {{ trans('lms::messages.quiz_questions', ['n' => trans_number(count($questions ?? []))]) }} •
            {{ trans('lms::messages.quiz_pass_score', ['n' => trans_number($quiz->passScore())]) }} •
            {{ trans('lms::messages.quiz_max_attempts', ['n' => trans_number($quiz->maxAttempts())]) }}
            @if($quiz->timeLimit() !== null)
                • {{ trans('lms::messages.quiz_time_limit', ['n' => trans_number($quiz->timeLimit())]) }}
            @endif
        </p>

        @if($alreadyPassed)
            <p><span class="badge done">{{ trans('lms::messages.already_passed') }}</span></p>
        @elseif($activeAttempt !== null)
            <a class="lms-btn" href="/lms/attempts/{{ $activeAttempt->id() }}">{{ trans('lms::messages.quiz_continue') }}</a>
        @elseif($remainingAttempts > 0)
            <form method="post" action="/lms/quizzes/{{ $quiz->id() }}/attempt">
                @csrf
                <button class="lms-btn" type="submit">{{ trans('lms::messages.quiz_start') }}</button>
            </form>
            <p class="muted">{{ trans('lms::messages.quiz_attempts_used', ['n' => trans_number(count($attempts))]) }}</p>
        @else
            <p><span class="badge pending">{{ trans('lms::messages.max_attempts_reached') }}</span></p>
        @endif
    </div>

    @if(count($attempts) > 0)
        <div class="lms-card">
            <h3>{{ trans('lms::messages.attempt_history') }}</h3>
            @foreach($attempts as $attempt)
                <div class="lesson-row">
                    <span class="grow">
                        <a href="/lms/attempts/{{ $attempt->id() }}/result">{{ jalali($attempt->startedAt(), 'Y/m/d H:i') }}</a>
                    </span>
                    <span class="meta">
                        {{ $attempt->status()->labelFa() }}
                        @if($attempt->isGraded())
                            — {{ trans_number($attempt->score()) }}/{{ trans_number($attempt->maxScore()) }}
                            @if($attempt->passed() === true)<span class="badge done">✔</span>@endif
                            @if($attempt->passed() === false)<span class="badge pending">✖</span>@endif
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
