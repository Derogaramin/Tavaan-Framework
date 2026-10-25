@extends('lms::layouts.lms')

@section('pageTitle', $success ? trans('lms::messages.payment_success') : trans('lms::messages.payment_failed'))

@section('content')
    <div class="lms-card" style="max-width:520px;margin:30px auto;text-align:center">
        <div style="font-size:3rem">{{ $success ? '✅' : '❌' }}</div>
        <h1 style="font-size:1.2rem;margin:8px 0">{{ $success ? trans('lms::messages.payment_success') : trans('lms::messages.payment_failed') }}</h1>
        <p class="muted">{{ $success ? trans('lms::messages.payment_success_hint') : ($message !== '' ? $message : trans('lms::messages.payment_failed_hint')) }}</p>
        @if($course !== null)
            <p style="margin-top:14px">
                @if($success)
                    <a class="lms-btn" href="/lms/learn/{{ $course->slug() }}">{{ trans('lms::messages.start_learning') }}</a>
                @elseif($enrollment !== null)
                    <a class="lms-btn ghost" href="/lms/pay/{{ $enrollment->id() }}">{{ trans('lms::messages.retry_payment') }}</a>
                @endif
            </p>
        @endif
    </div>
@endsection
