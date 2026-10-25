@extends('lms::layouts.lms')

@php
@endphp

@section('pageTitle', trans('lms::messages.quiz_result'))

@section('content')
<div style="max-width:720px;margin-inline:auto">
    <div class="lms-card" style="text-align:center">
        <h1>{{ trans('lms::messages.quiz_result') }}</h1>
        <p class="muted">{{ $quiz->title() }}</p>

        @if($attempt->isAwaitingManualGrading())
            <div style="font-size:3rem">⏳</div>
            <p>{{ trans('lms::messages.awaiting_grading') }}</p>
            <p class="muted">{{ trans('lms::messages.quiz_score') }} (خودکار): {{ trans_number($attempt->score()) }}/{{ trans_number($attempt->maxScore()) }}</p>
        @elseif($attempt->isGraded())
            <div style="font-size:3rem">{{ $attempt->passed() ? '🎉' : '😔' }}</div>
            <p style="font-size:1.3rem;font-weight:800">
                {{ trans('lms::messages.quiz_score') }}: {{ trans_number($attempt->score()) }}/{{ trans_number($attempt->maxScore()) }}
            </p>
            <p>
                @if($attempt->passed())
                    <span class="badge done">{{ trans('lms::messages.quiz_passed') }}</span>
                @else
                    <span class="badge pending">{{ trans('lms::messages.quiz_failed') }}</span>
                @endif
            </p>
        @endif

        @if($course !== null)
            <p><a class="lms-btn ghost" href="/lms/learn/{{ $course->slug() }}">{{ trans('lms::messages.go_to_learn') }}</a></p>
        @endif
    </div>

    @if(count($breakdown) > 0)
        <div class="lms-card">
            <h3>{{ trans('lms::messages.attempt_history') }}</h3>
            @foreach($breakdown as $i => $row)
                @php $q = $row['question']; @endphp
                <div class="qa-item">
                    <p><strong>{{ trans_number($i + 1) }}. {{ $q->body() }}</strong>
                        <span class="muted">({{ $q->type()->labelFa() }})</span>
                    </p>
                    <p class="muted">{{ trans('lms::messages.your_answer') }}:
                        @if($row['answer'] !== null && $row['answer'] !== '')
                            {{ $row['answer'] }}
                        @else
                            —
                        @endif
                    </p>
                    @if($row['auto'] !== null)
                        <p>{{ $row['auto']['correct'] ? '✅' : '❌' }} {{ trans_number($row['auto']['score']) }}/{{ trans_number($q->score()) }}</p>
                    @elseif($row['manual'] !== null)
                        <p>{{ trans_number($row['manual']['score']) }}/{{ trans_number($q->score()) }}
                            @if(($row['manual']['note'] ?? '') !== '')— <span class="muted">{{ $row['manual']['note'] }}</span>@endif
                        </p>
                    @elseif($row['is_manual'])
                        <p class="muted">⏳ {{ trans('lms::messages.awaiting_grading') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
