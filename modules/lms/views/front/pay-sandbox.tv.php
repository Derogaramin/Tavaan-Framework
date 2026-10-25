@extends('lms::layouts.lms')

@section('pageTitle', trans('lms::messages.sandbox_gateway'))

@section('content')
    <div class="lms-card" style="max-width:520px;margin:30px auto;text-align:center">
        <div style="font-size:3rem">🧪</div>
        <h1 style="font-size:1.2rem;margin:8px 0">{{ trans('lms::messages.sandbox_gateway') }}</h1>
        <p class="muted">{{ trans('lms::messages.sandbox_hint') }}</p>
        @if($enrollment !== null && $course !== null)
            <table style="margin:14px auto;max-width:360px;font-size:.88rem">
                <tbody>
                <tr><td>{{ trans('lms::messages.course') }}</td><td style="text-align:left"><strong>{{ $course->title() }}</strong></td></tr>
                <tr><td>{{ trans('lms::messages.amount') }}</td><td style="text-align:left"><strong>{{ trans_number($course->price()->minor) }} ریال</strong></td></tr>
                <tr><td>authority</td><td style="text-align:left;font-size:.75rem;word-break:break-all">{{ $authority }}</td></tr>
                </tbody>
            </table>
        @endif
        <div style="display:flex;gap:10px;justify-content:center;margin-top:14px">
            <a class="lms-btn" href="/lms/pay/callback?authority={{ urlencode($authority) }}&status=ok">✔ {{ trans('lms::messages.pay_now') }}</a>
            <a class="lms-btn danger" href="/lms/pay/callback?authority={{ urlencode($authority) }}&status=fail">✖ {{ trans('lms::messages.pay_cancel') }}</a>
        </div>
    </div>
@endsection
