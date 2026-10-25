@extends('crm::layouts.crm')

@php $trackMode = !empty($track); @endphp

@section('pageTitle', $trackMode ? trans('crm::messages.support_track') : trans('crm::messages.support_portal'))

@section('content')
<div class="crm-card">
    <h1>🎧 {{ trans('crm::messages.support_portal') }}</h1>
    <p class="muted">{{ trans('crm::messages.support_intro') }}</p>
</div>

@if(!$trackMode)
<div class="crm-card">
    <form method="post" action="/crm/support" class="crm-form">
        @csrf
        <div class="field">
            <label for="name">{{ trans('crm::messages.support_your_name') }} *</label>
            <input type="text" id="name" name="name" required maxlength="100">
        </div>
        <div class="field">
            <label for="email">{{ trans('crm::messages.support_your_email') }} *</label>
            <input type="email" id="email" name="email" required maxlength="190" dir="ltr">
        </div>
        <div class="field">
            <label for="subject">{{ trans('crm::messages.ticket_subject') }} *</label>
            <input type="text" id="subject" name="subject" required maxlength="255">
        </div>
        <div class="field">
            <label for="category">{{ trans('crm::messages.category') }}</label>
            <input type="text" id="category" name="category" maxlength="50">
        </div>
        <div class="field">
            <label for="body">{{ trans('crm::messages.ticket_body') }} *</label>
            <textarea id="body" name="body" required minlength="5" maxlength="20000"></textarea>
        </div>
        <button class="crm-btn" type="submit">{{ trans('crm::messages.support_submit') }}</button>
    </form>
</div>
@endif

<div class="crm-card">
    <h2>{{ trans('crm::messages.support_track') }}</h2>
    <form method="get" action="" class="crm-form" id="track-form">
        <div class="field">
            <label for="number">{{ trans('crm::messages.ticket_number') }}</label>
            <input type="text" id="number" placeholder="TKT-…" dir="ltr" required>
        </div>
        <button class="crm-btn ghost" type="submit">{{ trans('crm::messages.support_track_submit') }}</button>
    </form>
</div>

@push('head')
@verbatim
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('track-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var n = document.getElementById('number').value.trim();
            if (n !== '') window.location.href = '/crm/support/' + encodeURIComponent(n);
        });
    });
</script>
@endverbatim
@endpush
@endsection
