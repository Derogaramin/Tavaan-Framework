@extends('lms::layouts.lms')

@section('pageTitle', trans('lms::messages.my_courses'))

@section('content')
<h1>{{ trans('lms::messages.my_courses') }}</h1>

@forelse($items as $item)
    @php
        $enrollment = $item['enrollment'];
        $course = $item['course'];
        $percent = (int) $enrollment->progressPercent();
    @endphp
    <div class="lms-card">
        <div class="lesson-row" style="border:0;padding:0">
            <span class="grow">
                <h3 style="margin:0"><a href="/lms/learn/{{ $course->slug() }}">{{ $course->title() }}</a></h3>
                <p class="muted" style="margin:.2rem 0">
                    👤 {{ $item['instructor'] }} •
                    {{ $enrollment->status()->labelFa() }}
                    @if($item['certificate'] !== null && $item['certificate']->isValid())
                        • <a href="{{ $item['certificate']->verifyUrl() }}">🏅 {{ trans('lms::messages.certificate_view') }}</a>
                    @endif
                </p>
                <div class="progress-track" style="max-width:420px">
                    <div class="progress-fill" style="width: {{ $percent }}%"></div>
                </div>
                <span class="muted">{{ trans('lms::messages.your_progress') }}: {{ trans_number($percent) }}٪</span>
            </span>
            <span class="meta">
                @if($enrollment->isPendingPayment())
                    <a class="lms-btn sm" href="/lms/pay/{{ $enrollment->id() }}">{{ trans('lms::messages.continue_payment') }}</a>
                @elseif($enrollment->status()->grantsAccess())
                    <a class="lms-btn sm" href="/lms/learn/{{ $course->slug() }}">{{ trans('lms::messages.continue_learning') }}</a>
                @endif
            </span>
        </div>
    </div>
@empty
    <div class="lms-card">
        <p class="muted">{{ trans('lms::messages.no_enrollments') }}</p>
        <a class="lms-btn" href="/lms">{{ trans('lms::messages.browse_courses') }}</a>
    </div>
@endforelse
@endsection
