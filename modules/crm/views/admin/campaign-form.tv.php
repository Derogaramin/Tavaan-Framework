@extends('crm::layouts.admin')

@php $isEdit = $campaign !== null; @endphp

@section('title', $isEdit ? $campaign->name() : 'کمپین جدید')

@section('content')
<div class="topline">
    <h1>{{ $isEdit ? '📣 ' . $campaign->name() : '📣 کمپین جدید' }}</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/campaigns">← فهرست</a>
    @if($isEdit)
        <span class="badge {{ $campaign->status() === \Modules\Crm\Domain\Enums\CampaignStatus::Sent ? 'green' : 'blue' }}">{{ $campaign->status()->labelFa() }}</span>
    @endif
</div>

@if($isEdit)
    <div class="stats">
        <div class="stat"><div class="num">{{ trans_number($campaign->sentCount()) }}</div><div class="muted">ارسال‌شده</div></div>
        <div class="stat"><div class="num">{{ trans_number($campaign->failedCount()) }}</div><div class="muted">ناموفق</div></div>
    </div>
@endif

<div class="card">
    <form method="post" action="{{ $isEdit ? '/admin/crm/campaigns/' . $campaign->id() : '/admin/crm/campaigns' }}">
        @csrf
        <div class="form-grid">
            <label>{{ trans('crm::messages.campaign_name') }} *
                <input type="text" name="name" value="{{ $isEdit ? $campaign->name() : '' }}" required maxlength="190">
            </label>
            <label>{{ trans('crm::messages.campaign_type') }}
                <select name="type" {{ $isEdit ? 'disabled' : '' }}>
                    <option value="email" {{ $isEdit && $campaign->type() === 'email' ? 'selected' : '' }}>✉️ ایمیل</option>
                    <option value="sms" {{ $isEdit && $campaign->type() === 'sms' ? 'selected' : '' }}>📱 پیامک (log-only)</option>
                </select>
            </label>
            <label class="full">{{ trans('crm::messages.campaign_template') }} *
                <textarea name="template" rows="6" required>{{ $isEdit ? $campaign->template() : "سلام {first_name}،\n\n" }}</textarea>
                <span class="muted">{{ trans('crm::messages.campaign_template_hint') }}</span>
            </label>
            <label class="full">{{ trans('crm::messages.campaign_audience') }}
                <input type="text" name="audience" dir="ltr" value="{{ $isEdit ? htmlspecialchars(json_encode($campaign->audienceQuery(), JSON_UNESCAPED_UNICODE)) : '' }}">
                <span class="muted">{{ trans('crm::messages.campaign_audience_hint') }}</span>
            </label>
            <label>{{ trans('crm::messages.campaign_scheduled_at') }}
                <input type="datetime-local" name="scheduled_at" value="{{ $isEdit && $campaign->scheduledAt() !== null ? $campaign->scheduledAt()->format('Y-m-d\TH:i') : '' }}">
            </label>
        </div>
        <p style="margin-top:12px"><button class="btn" type="submit">{{ trans('crm::messages.save') }}</button></p>
    </form>
</div>

@if($isEdit && $campaign->status() !== \Modules\Crm\Domain\Enums\CampaignStatus::Sent)
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.campaign_send') }}</h3>
    <p class="muted">ارسال فوری به مخاطبان منطبق با audience (idempotent — ارسال دوبارهٔ همان کمپین اثر ندارد).</p>
    <form method="post" action="/admin/crm/campaigns/{{ $campaign->id() }}/send" onsubmit="return confirm('کمپین اکنون ارسال شود؟')">
        @csrf
        <button class="btn success" type="submit">🚀 {{ trans('crm::messages.campaign_send') }}</button>
    </form>
</div>
@endif
@endsection
