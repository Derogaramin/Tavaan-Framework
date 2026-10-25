@extends('crm::layouts.admin')

@section('title', $contact->fullName())

@section('content')
<div class="topline">
    <h1>👤 {{ $contact->fullName() }}</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/contacts/{{ $contact->id() }}/edit">ویرایش</a>
    <form class="inline" method="post" action="/admin/crm/contacts/{{ $contact->id() }}" onsubmit="return confirm('حذف مخاطب؟')">
        @csrf
        @method('DELETE')
        <button class="btn danger" type="submit">حذف</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <tbody>
        <tr><th>شرکت</th><td>{{ $contact->company() ?? '—' }} {{ $contact->title() !== null ? '(' . $contact->title() . ')' : '' }}</td></tr>
        <tr><th>ایمیل</th><td dir="ltr">{{ $contact->email() ?? '—' }}</td></tr>
        <tr><th>موبایل</th><td dir="ltr">{{ $contact->mobile() ?? '—' }}</td></tr>
        <tr><th>{{ trans('crm::messages.national_id') }}</th><td dir="ltr">{{ $nationalIdMasked }}</td></tr>
        <tr><th>منبع</th><td><span class="badge blue">{{ $contact->source() }}</span></td></tr>
        <tr><th>مسئول</th><td>{{ $ownerName ?? '—' }}</td></tr>
        <tr><th>{{ trans('crm::messages.tags') }}</th><td>@forelse($contact->tags() as $tag)<span class="badge">{{ $tag }}</span> @empty—@endforelse</td></tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.assign') }}</h3>
    <form method="post" action="/admin/crm/contacts/{{ $contact->id() }}/assign" style="display:flex;gap:8px">
        @csrf
        <select name="owner_id" style="max-width:260px">
            <option value="0">— بدون مسئول —</option>
            @foreach($users as $u)
                <option value="{{ $u['id'] }}" {{ $contact->ownerId() === $u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
            @endforeach
        </select>
        <button class="btn ghost" type="submit">{{ trans('crm::messages.assign') }}</button>
    </form>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🎯 سرنخ‌ها</h3>
    @forelse($leads as $lead)
        <div style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <span style="flex:1"><a href="/admin/crm/leads/{{ $lead->id() }}">{{ $lead->title() }}</a>
                <span class="badge">{{ $lead->status()->labelFa() }}</span></span>
            <span class="muted">{{ trans_number($lead->valueMinor()) }} ریال</span>
        </div>
    @empty
        <p class="muted">—</p>
    @endforelse
    <p style="margin-top:8px"><a class="btn sm" href="/admin/crm/leads/create?contact_id={{ $contact->id() }}">+ سرنخ جدید</a></p>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">📈 فرصت‌ها</h3>
    @forelse($opportunities as $opp)
        <div style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <span style="flex:1"><a href="/admin/crm/opportunities/{{ $opp->id() }}">{{ $opp->title() }}</a>
                <span class="badge blue">{{ $opp->stage()->labelFa() }}</span></span>
            <span class="muted">{{ trans_number($opp->valueMinor()) }} ریال • {{ trans_number($opp->probability()) }}٪</span>
        </div>
    @empty
        <p class="muted">—</p>
    @endforelse
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🎫 تیکت‌ها</h3>
    @forelse($tickets as $ticket)
        <div style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <span style="flex:1"><a href="/admin/crm/tickets/{{ $ticket->id() }}">{{ $ticket->subject() }}</a>
                <span class="badge">{{ $ticket->status()->labelFa() }}</span></span>
            <span class="muted" dir="ltr">{{ $ticket->ticketNumber() }}</span>
        </div>
    @empty
        <p class="muted">—</p>
    @endforelse
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🕘 فعالیت‌ها</h3>
    <div class="timeline">
        @forelse($activities as $activity)
            <div class="ev">
                <strong>{{ $activity->type()->icon() }} {{ $activity->subject() }}</strong>
                @if($activity->body() !== null)<p class="muted">{{ $activity->body() }}</p>@endif
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
            <input type="hidden" name="contact_id" value="{{ $contact->id() }}">
            <label>{{ trans('crm::messages.activity_type') }}
                <select name="activity_type">
                    @foreach(\Modules\Crm\Domain\Enums\ActivityType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->labelFa() }}</option>
                    @endforeach
                </select>
            </label>
            <label>{{ trans('crm::messages.activity_subject') }} *<input type="text" name="subject" required></label>
            <label class="full">{{ trans('crm::messages.activity_body') }}<textarea name="body" rows="2"></textarea></label>
            <label>{{ trans('crm::messages.due_at') }}<input type="datetime-local" name="due_at"></label>
            <p style="grid-column:1/-1"><button class="btn sm" type="submit">ثبت</button></p>
        </form>
    </details>
</div>
@endsection
