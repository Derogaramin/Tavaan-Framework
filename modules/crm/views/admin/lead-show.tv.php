@extends('crm::layouts.admin')

@section('title', $lead->title())

@section('content')
<div class="topline">
    <h1>🎯 {{ $lead->title() }}</h1>
    <span class="spacer"></span>
    <span class="badge {{ $lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::Converted ? 'green' : ($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::Lost ? 'red' : 'blue') }}">
        {{ $lead->status()->labelFa() }}
    </span>
    <span class="score-pill {{ $lead->score() >= 70 ? 'score-hot' : ($lead->score() >= 40 ? 'score-warm' : 'score-cold') }}">
        امتیاز {{ trans_number($lead->score()) }}
    </span>
</div>

<div class="card">
    <table class="data">
        <tbody>
        <tr><th>مخاطب</th><td>@if($contact !== null)<a href="/admin/crm/contacts/{{ $contact->id() }}">{{ $contact->fullName() }}</a>@else—@endif</td></tr>
        <tr><th>ارزش</th><td>{{ trans_number($lead->valueMinor()) }} {{ $lead->currency() === 'IRR' ? 'ریال' : $lead->currency() }}</td></tr>
        <tr><th>منبع</th><td><span class="badge blue">{{ $lead->source() }}</span></td></tr>
        <tr><th>مسئول</th><td>{{ $lead->ownerId() ?? '—' }}</td></tr>
        @if($lead->convertedTo() !== null)
            <tr><th>فرصت مقصد</th><td><a href="/admin/crm/opportunities/{{ $lead->convertedTo() }}">مشاهدهٔ فرصت</a></td></tr>
        @endif
        @if($lead->lostReason() !== null)
            <tr><th>دلیل ازدست‌رفتن</th><td>{{ $lead->lostReason() }}</td></tr>
        @endif
        <tr><th>تاریخ</th><td>{{ jalali($lead->createdAt(), 'Y/m/d H:i') }}</td></tr>
        </tbody>
    </table>
</div>

@if($lead->status()->isOpen())
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:10px">عملیات</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @if($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::New)
            <form class="inline" method="post" action="/admin/crm/leads/{{ $lead->id() }}/qualify">
                @csrf
                <button class="btn success" type="submit">{{ trans('crm::messages.qualify') }}</button>
            </form>
        @endif

        @if($lead->status() === \Modules\Crm\Domain\Enums\LeadStatus::Qualified)
            <form method="post" action="/admin/crm/leads/{{ $lead->id() }}/convert" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                @csrf
                <label style="font-size:.85rem">عنوان فرصت
                    <input type="text" name="title" value="{{ $lead->title() }}" style="min-width:220px">
                </label>
                <label style="font-size:.85rem">ارزش (ریال)
                    <input type="number" name="value" value="{{ $lead->valueMinor() }}" min="0" style="max-width:140px">
                </label>
                <button class="btn" type="submit">{{ trans('crm::messages.convert_to_opportunity') }}</button>
            </form>
        @endif

        <form method="post" action="/admin/crm/leads/{{ $lead->id() }}/lost" style="display:flex;gap:8px;align-items:end">
            @csrf
            <label style="font-size:.85rem">{{ trans('crm::messages.lost_reason') }}
                <input type="text" name="reason" style="min-width:180px">
            </label>
            <button class="btn danger" type="submit">{{ trans('crm::messages.mark_lost') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.assign') }}</h3>
    <form method="post" action="/admin/crm/leads/{{ $lead->id() }}/assign" style="display:flex;gap:8px">
        @csrf
        <select name="owner_id" style="max-width:260px">
            @foreach($users as $u)
                <option value="{{ $u['id'] }}" {{ $lead->ownerId() === $u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
            @endforeach
        </select>
        <button class="btn ghost" type="submit">{{ trans('crm::messages.assign') }}</button>
    </form>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">ویرایش</h3>
    <form method="post" action="/admin/crm/leads/{{ $lead->id() }}" class="form-grid">
        @csrf
        <label class="full">{{ trans('crm::messages.lead_title') }} *
            <input type="text" name="title" value="{{ $lead->title() }}" required>
        </label>
        <label>{{ trans('crm::messages.lead_value') }}
            <input type="number" name="value" value="{{ $lead->valueMinor() }}" min="0">
        </label>
        <p style="grid-column:1/-1"><button class="btn ghost" type="submit">{{ trans('crm::messages.save') }}</button></p>
    </form>
</div>
@endif
@endsection
