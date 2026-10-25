@extends('lms::layouts.lms')

@php
@endphp

@section('pageTitle', $course->title() . ' — ' . trans('lms::messages.lms_title'))

@section('metaTags')
<meta name="description" content="{{ mb_substr(strip_tags((string) $course->summary()), 0, 160) }}">
<meta property="og:title" content="{{ $course->title() }}">
<meta property="og:type" content="article">
@endsection

@section('content')
<div class="lms-grid cols-2">
    <div>
        <div class="lms-card">
            <h1>{{ $course->title() }}</h1>
            <p class="muted">
                <span class="badge level">{{ $course->level()->labelFa() }}</span>
                @if($course->isFree())
                    <span class="badge free">{{ trans('lms::messages.free') }}</span>
                @else
                    <span class="badge paid">{{ trans_number($course->price()->minor) }} {{ $course->price()->currency === 'IRR' ? 'ریال' : $course->price()->currency }}</span>
                @endif
            </p>
            @if($course->summary())
                <p>{!! $course->summary() !!}</p>
            @endif
            <p class="muted">
                👤 {{ trans('lms::messages.instructor') }}: <strong>{{ $instructor }}</strong> •
                ⏱ {{ trans('lms::messages.duration_minutes', ['n' => trans_number($course->totalDuration())]) }} •
                🧑‍🎓 {{ trans_number($course->studentCount()) }}
                @if($category !== null)
                    • 🗂 <a href="/lms/category/{{ $category->slug() }}">{{ $category->title() }}</a>
                @endif
            </p>
        </div>

        @if($course->description())
            <div class="lms-card">
                <h2>{{ trans('lms::messages.course_description') }}</h2>
                {!! $course->description() !!}
            </div>
        @endif

        <div class="lms-card">
            <h2>{{ trans('lms::messages.course_syllabus') }}</h2>
            @forelse($structure as $branch)
                <h3>📁 {{ $branch['section']->title() }}</h3>
                @foreach($branch['lessons'] as $lesson)
                    <div class="lesson-row">
                        <span>{{ $lesson->type()->icon() }}</span>
                        <span class="grow">
                            {{ $lesson->title() }}
                            @if($lesson->isFreePreview())
                                <span class="badge free">{{ trans('lms::messages.free_preview') }}</span>
                                <a class="lms-btn ghost sm" href="/lms/lessons/{{ $lesson->id() }}">{{ trans('lms::messages.start_learning') }}</a>
                            @endif
                        </span>
                        <span class="meta">{{ $lesson->duration() > 0 ? trans('lms::messages.duration_minutes', ['n' => trans_number($lesson->duration())]) : '' }}</span>
                    </div>
                @endforeach
            @empty
                <p class="muted">—</p>
            @endforelse
        </div>

        @if(count($prerequisites) > 0)
            <div class="lms-card">
                <h2>{{ trans('lms::messages.prerequisites') }}</h2>
                <p class="muted">{{ trans('lms::messages.prerequisite_hint') }}</p>
                <ul>
                    @foreach($prerequisites as $prereq)
                        <li><a href="/lms/courses/{{ $prereq->slug() }}">{{ $prereq->title() }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(count($related) > 0)
            <div class="lms-card">
                <h2>{{ trans('lms::messages.related_courses') }}</h2>
                @foreach($related as $rel)
                    <div class="lesson-row">
                        <span class="grow"><a href="/lms/courses/{{ $rel->slug() }}">{{ $rel->title() }}</a></span>
                        <span class="meta">
                            @if($rel->isFree()){{ trans('lms::messages.free') }}@else{{ trans_number($rel->price()->minor) }} ریال@endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <aside>
        <div class="lms-card">
            <h3>{{ trans('lms::messages.enroll') }}</h3>
            @php
                $status = $enrollment?->status();
            @endphp
            @if($enrollment !== null && ($status === \Modules\Lms\Domain\Enums\EnrollmentStatus::Active || $status === \Modules\Lms\Domain\Enums\EnrollmentStatus::Completed))
                <p class="muted">{{ trans('lms::messages.enrolled') }}</p>
                <p>
                    <span class="badge progress">{{ trans('lms::messages.your_progress') }}: {{ trans_number((int) $enrollment->progressPercent()) }}٪</span>
                    @if($status === \Modules\Lms\Domain\Enums\EnrollmentStatus::Completed)
                        <span class="badge done">{{ trans('lms::messages.course_completed') }}</span>
                    @endif
                </p>
                <a class="lms-btn" href="/lms/learn/{{ $course->slug() }}">{{ trans('lms::messages.continue_learning') }}</a>
                @if($certificate !== null && $certificate->isValid())
                    <p><a class="lms-btn ghost sm" href="{{ $certificate->verifyUrl() }}">🏅 {{ trans('lms::messages.certificate_view') }}</a></p>
                @endif
            @elseif($enrollment !== null && $status === \Modules\Lms\Domain\Enums\EnrollmentStatus::PendingPayment)
                <p class="muted">{{ trans('lms::messages.continue_payment') }}</p>
                <a class="lms-btn" href="/lms/pay/{{ $enrollment->id() }}">{{ trans('lms::messages.continue_payment') }}</a>
            @else
                <p style="font-size:1.4rem; font-weight:800">
                    @if($course->isFree())
                        {{ trans('lms::messages.free') }}
                    @else
                        {{ trans_number($course->price()->minor) }} <span style="font-size:.85rem">ریال</span>
                    @endif
                </p>
                @auth
                    <form method="post" action="/lms/courses/{{ $course->id() }}/enroll">
                        @csrf
                        <button class="lms-btn" type="submit">
                            {{ $course->isFree() ? trans('lms::messages.enroll_free') : trans('lms::messages.enroll_paid') }}
                        </button>
                    </form>
                @else
                    <a class="lms-btn" href="/login">{{ trans('lms::messages.login_to_enroll') }}</a>
                @endauth
            @endif
        </div>
    </aside>
</div>
@endsection
