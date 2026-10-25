@extends('lms::layouts.lms')

@php
    $percent = (int) $enrollment->progressPercent();
@endphp

@section('pageTitle', $course->title() . ' — ' . trans('lms::messages.learning_space'))

@section('content')
<div class="lms-grid cols-2">
    <div>
        <div class="lms-card">
            <h1>{{ $course->title() }}</h1>
            <p class="muted">👤 {{ $instructor }}</p>
            <div class="progress-track" style="max-width:520px">
                <div class="progress-fill" style="width: {{ $percent }}%"></div>
            </div>
            <p class="muted">{{ trans('lms::messages.your_progress') }}: {{ trans_number($percent) }}٪
                @if($enrollment->isCompleted())
                    — <span class="badge done">{{ trans('lms::messages.course_completed') }}</span>
                @endif
            </p>
            @if($certificate !== null && $certificate->isValid())
                <p><a class="lms-btn success sm" href="{{ $certificate->verifyUrl() }}">🏅 {{ trans('lms::messages.certificate_view') }}</a></p>
            @endif
        </div>

        @forelse($structure as $branch)
            <div class="lms-card">
                <h3>📁 {{ $branch['section']->title() }}</h3>
                @foreach($branch['lessons'] as $lesson)
                    @php $done = isset($completed[$lesson->id()]); @endphp
                    <div class="lesson-row">
                        <span>{{ $done ? '✅' : $lesson->type()->icon() }}</span>
                        <span class="grow">
                            <a href="/lms/lessons/{{ $lesson->id() }}">{{ $lesson->title() }}</a>
                            @if($done)<span class="badge done">{{ trans('lms::messages.lesson_completed') }}</span>@endif
                        </span>
                        <span class="meta">{{ $lesson->duration() > 0 ? trans('lms::messages.duration_minutes', ['n' => trans_number($lesson->duration())]) : '' }}</span>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="lms-card"><p class="muted">—</p></div>
        @endforelse
    </div>

    <aside>
        <div class="lms-card">
            <strong>{{ trans('lms::messages.course') }}</strong>
            <p class="muted">
                <a href="/lms/courses/{{ $course->slug() }}">{{ trans('lms::messages.course_description') }}</a>
            </p>
        </div>
    </aside>
</div>
@endsection
