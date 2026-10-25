@extends('crm::layouts.admin')

@section('title', $opportunity->title())

@section('content')
<div class="topline">
    <h1>📈 {{ $opportunity->title() }}</h1>
    <span class="spacer"></span>
    @if($opportunity->stage()->isWon())<span class="badge green">{{ $opportunity->stage()->labelFa() }} 🎉</span>
    @elseif($opportunity->stage() === \Modules\Crm\Domain\Enums\OpportunityStage::ClosedLost)<span class="badge red">{{ $opportunity->stage()->labelFa() }}</span>
    @else<span class="badge blue">{{ $opportunity->stage()->labelFa() }} — {{ trans_number($opportunity->probability()) }}٪</span>@endif
</div>

<div class="card">
    <table class="data">
        <tbody>
        <tr><th>مخاطب</th><td>@if($contact !== null)<a href="/admin/crm/contacts/{{ $contact->id() }}">{{ $contact->fullName() }}</a>@else—@endif</td></tr>
        <tr><th>ارزش</th><td>{{ trans_number($opportunity->valueMinor()) }} {{ $opportunity->currency() === 'IRR' ? 'ریال' : $opportunity->currency() }}</td></tr>
        <tr><th>مقدار وزنی</th><td>{{ trans_number($opportunity->weightedValue()) }} ریال</td></tr>
        <tr><th>مسئول</th><td>{{ $ownerName ?? '—' }}</td></tr>
        <tr><th>{{ trans('crm::messages.expected_close_date') }}</th><td>{{ $opportunity->expectedCloseDate() ?? '—' }}</td></tr>
        @if($opportunity->lostReason() !== null)
            <tr><th>{{ trans('crm::messages.lost_reason') }}</th><td>{{ $opportunity->lostReason() }}</td></tr>
        @endif
        @if($opportunity->workflowInstanceId() !== null)
            <tr><th>Workflow</th><td dir="ltr" class="muted">{{ $opportunity->workflowInstanceId() }}</td></tr>
        @endif
        </tbody>
    </table>
</div>

@if(!$opportunity->stage()->isClosed())
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:10px">{{ trans('crm::messages.move_stage') }}</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach(\Modules\Crm\Domain\Enums\OpportunityStage::cases() as $stage)
            @if($stage !== $opportunity->stage() && !$stage->isClosed() && $opportunity->stage()->canMoveTo($stage))
                <form class="inline" method="post" action="/admin/crm/opportunities/{{ $opportunity->id() }}/move">
                    @csrf
                    <input type="hidden" name="stage" value="{{ $stage->value }}">
                    <button class="btn ghost sm" type="submit">← {{ $stage->labelFa() }} ({{ trans_number($stage->defaultProbability()) }}٪)</button>
                </form>
            @endif
        @endforeach

        <form class="inline" method="post" action="/admin/crm/opportunities/{{ $opportunity->id() }}/move">
            @csrf
            <input type="hidden" name="stage" value="closed_won">
            <button class="btn success" type="submit">{{ trans('crm::messages.win') }}</button>
        </form>

        <form method="post" action="/admin/crm/opportunities/{{ $opportunity->id() }}/move" style="display:flex;gap:6px;align-items:center">
            @csrf
            <input type="hidden" name="stage" value="closed_lost">
            <input type="text" name="lost_reason" placeholder="{{ trans('crm::messages.lost_reason') }}…" required style="max-width:200px">
            <button class="btn danger" type="submit">{{ trans('crm::messages.lose') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">ویرایش / تخصیص</h3>
    <form method="post" action="/admin/crm/opportunities/{{ $opportunity->id() }}" class="form-grid">
        @csrf
        <label class="full">{{ trans('crm::messages.opportunity_title') }}
            <input type="text" name="title" value="{{ $opportunity->title() }}" required>
        </label>
        <label>{{ trans('crm::messages.opportunity_value') }}
            <input type="number" name="value" value="{{ $opportunity->valueMinor() }}" min="0">
        </label>
        <label>{{ trans('crm::messages.expected_close_date') }}
            <input type="date" name="expected_close_date" value="{{ $opportunity->expectedCloseDate() ?? '' }}">
        </label>
        <p style="grid-column:1/-1"><button class="btn ghost" type="submit">{{ trans('crm::messages.save') }}</button></p>
    </form>
    <form method="post" action="/admin/crm/opportunities/{{ $opportunity->id() }}/assign" style="display:flex;gap:8px;margin-top:10px">
        @csrf
        <select name="owner_id" style="max-width:260px">
            @foreach($users as $u)
                <option value="{{ $u['id'] }}" {{ $opportunity->ownerId() === $u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
            @endforeach
        </select>
        <button class="btn ghost" type="submit">{{ trans('crm::messages.assign') }}</button>
    </form>
</div>
@endif

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🕘 فعالیت‌ها</h3>
    <div class="timeline">
        @forelse($activities as $activity)
            <div class="ev">
                <strong>{{ $activity->type()->icon() }} {{ $activity->subject() }}</strong>
                <span class="muted">{{ jalali($activity->createdAt(), 'Y/m/d H:i') }}</span>
            </div>
        @empty
            <p class="muted">—</p>
        @endforelse
    </div>
    <details style="margin-top:10px">
        <summary class="btn sm ghost" style="cursor:pointer">{{ trans('crm::messages.log_activity') }}</summary>
        <form method="post" action="/admin/crm/activities" class="form-grid" style="margin-top:8px">
            @csrf
            <input type="hidden" name="opportunity_id" value="{{ $opportunity->id() }}">
            <input type="hidden" name="contact_id" value="{{ $opportunity->contactId() }}">
            <label>{{ trans('crm::messages.activity_type') }}
                <select name="activity_type">
                    @foreach(\Modules\Crm\Domain\Enums\ActivityType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->labelFa() }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ trans('crm::messages.activity_subject') }} *<input type="text" name="subject" required></label>
            <label class="full">{{ trans('crm::messages.activity_body') }}<textarea name="body" rows="2"></textarea></label>
            <p style="grid-column:1/-1"><button class="btn sm" type="submit">ثبت</button></p>
        </form>
    </details>
</div>
@endsection
