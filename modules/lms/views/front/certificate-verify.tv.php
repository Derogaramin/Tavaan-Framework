@extends('lms::layouts.lms')

@php
@endphp

@section('pageTitle', trans('lms::messages.certificate_verify_title'))

@section('content')
<div style="max-width:640px;margin-inline:auto">
    <div class="lms-card">
        <h1>🏅 {{ trans('lms::messages.certificate_verify_title') }}</h1>

        <form method="get" action="/lms/certificates/lookup" class="lms-form" onsubmit="return lmsVerifyRedirect(this)">
            <div class="field">
                <label for="serial">{{ trans('lms::messages.certificate_verify_search') }}</label>
                <input type="text" id="serial" name="serial" value="{{ $serial }}" placeholder="TVN-LMS-XXXXXXXXXX-X" dir="ltr" required>
            </div>
            <button class="lms-btn" type="submit">{{ trans('lms::messages.verify_certificate') }}</button>
        </form>
    </div>

    @if($result !== null)
        <div class="lms-card" style="text-align:center">
            @if($result['valid'])
                <div style="font-size:3rem">✅</div>
                <h2>{{ trans('lms::messages.certificate_verify_valid') }}</h2>
            @elseif(($result['reason'] ?? '') === 'revoked')
                <div style="font-size:3rem">⛔</div>
                <h2>{{ trans('lms::messages.certificate_verify_revoked') }}</h2>
            @else
                <div style="font-size:3rem">🔍</div>
                <h2>{{ trans('lms::messages.certificate_verify_notfound') }}</h2>
            @endif

            @if($result['certificate'] !== null)
                @php $certificate = $result['certificate']; @endphp
                <table style="margin:14px auto;font-size:.9rem;text-align:start">
                    <tbody>
                    <tr><td>{{ trans('lms::messages.certificate_serial') }}</td><td dir="ltr"><strong>{{ $certificate->serial() }}</strong></td></tr>
                    <tr><td>{{ trans('lms::messages.certificate_holder') }}</td><td><strong>{{ $result['studentName'] }}</strong></td></tr>
                    <tr><td>{{ trans('lms::messages.certificate_course') }}</td><td><strong>{{ $result['courseTitle'] }}</strong></td></tr>
                    <tr><td>{{ trans('lms::messages.certificate_issued_at') }}</td><td>{{ jalali($certificate->issuedAt(), 'Y/m/d') }}</td></tr>
                    </tbody>
                </table>
                @if($result['valid'])
                    <p><a class="lms-btn ghost" href="/lms/certificates/{{ $certificate->serial() }}/document">{{ trans('lms::messages.certificate_view') }}</a></p>
                @endif
            @endif
        </div>
    @endif
</div>

@push('head')
@verbatim
<script>
    // فرم verify — ارسال به مسیر RESTful (بدون ساخت روت اضافی)
    function lmsVerifyRedirect(form) {
        var serial = form.querySelector('#serial').value.trim();
        if (serial !== '') { window.location.href = '/lms/certificates/' + encodeURIComponent(serial); }
        return false;
    }
</script>
@endverbatim
@endpush
@endsection
