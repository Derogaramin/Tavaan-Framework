@extends('crm::layouts.admin')

@section('title', $ticket->subject())

@section('content')
<div class="topline">
    <h1>🎫 {{ $ticket->subject() }}</h1>
    <span class="spacer"></span>
    <span class="muted" dir="ltr">{{ $ticket->ticketNumber() }}</span>
    @if($ticket->isEscalated())<span class="badge red">{{ trans('crm::messages.escalated') }}</span>@endif
</div>

<div class="card">
    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.88rem">
        <span><strong>وضعیت:</strong> {{ $ticket->status()->labelFa() }}</span>
        <span><strong>اولویت:</strong> {{ $ticket->priority()->labelFa() }}</span>
        <span><strong>کارشناس:</strong> {{ $assigneeName ?? 'تخصیص نیافته' }}</span>
        @if($ticket->category() !== null)<span><strong>دسته:</strong> {{ $ticket->category() }}</span>@endif
        @if($contact !== null)<span><strong>مخاطب:</strong> <a href="/admin/crm/contacts/{{ $contact->id() }}">{{ $contact->fullName() }}</a></span>@endif
    </div>
    @if($slaMinutesLeft !== null && $ticket->status()->slaRunning())
        <p style="margin-top:8px">
            @if($slaMinutesLeft < 0)
                <span class="badge red">{{ trans('crm::messages.sla_breached') }} — {{ trans_number(abs($slaMinutesLeft)) }} دقیقه تأخیر</span>
            @else
                <span class="badge amber">{{ trans('crm::messages.sla_remaining') }}: {{ trans_number($slaMinutesLeft) }} دقیقه</span>
            @endif
        </p>
    @endif
    <p style="margin-top:10px">{{ $ticket->body() }}</p>
    <p class="muted">{{ jalali($ticket->createdAt(), 'Y/m/d H:i') }}</p>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">عملیات</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <form method="post" action="/admin/crm/tickets/{{ $ticket->id() }}/assign" style="display:flex;gap:6px;align-items:end">
            @csrf
            <label style="font-size:.82rem">{{ trans('crm::messages.assigned_to') }}
                <select name="assigned_to" style="min-width:180px">
                    <option value="0">— تخصیص نیافته —</option>
                    @foreach($users as $u)
                        <option value="{{ $u['id'] }}" {{ $ticket->assignedTo() === $u['id'] ? 'selected' : '' }}>{{ $u['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn ghost sm" type="submit">{{ trans('crm::messages.assign') }}</button>
        </form>

        <form method="post" action="/admin/crm/tickets/{{ $ticket->id() }}/priority" style="display:flex;gap:6px;align-items:end">
            @csrf
            <label style="font-size:.82rem">{{ trans('crm::messages.priority') }}
                <select name="priority" style="min-width:120px">
                    @foreach(\Modules\Crm\Domain\Enums\TicketPriority::cases() as $p)
                        <option value="{{ $p->value }}" {{ $ticket->priority() === $p ? 'selected' : '' }}>{{ $p->labelFa() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn ghost sm" type="submit">ثبت</button>
        </form>

        @if($ticket->status() !== \Modules\Crm\Domain\Enums\TicketStatus::Resolved && $ticket->status() !== \Modules\Crm\Domain\Enums\TicketStatus::Closed)
            <form class="inline" method="post" action="/admin/crm/tickets/{{ $ticket->id() }}/resolve">
                @csrf
                <button class="btn success" type="submit">{{ trans('crm::messages.resolve') }}</button>
            </form>
        @endif
        @if($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Resolved)
            <form class="inline" method="post" action="/admin/crm/tickets/{{ $ticket->id() }}/close">
                @csrf
                <button class="btn warn" type="submit">{{ trans('crm::messages.close_ticket') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.replies') }}</h3>
    @forelse($replies as $reply)
        <div style="border-bottom:1px dashed #e2e8f0;padding:8px 0;{{ $reply->isInternal() ? 'background:#fffbeb;border-radius:8px;padding-inline:10px' : ($reply->isAgent() ? 'background:#f0f9ff;border-radius:8px;padding-inline:10px' : '') }}">
            <strong>{{ $reply->isAgent() ? '🛠 کارشناس' : '👤 مشتری' }}</strong>
            @if($reply->isInternal())<span class="badge amber">{{ trans('crm::messages.internal_note') }}</span>@endif
            <span class="muted">{{ jalali($reply->createdAt(), 'Y/m/d H:i') }}</span>
            <p>{{ $reply->body() }}</p>
        </div>
    @empty
        <p class="muted">—</p>
    @endforelse

    @if($ticket->status() !== \Modules\Crm\Domain\Enums\TicketStatus::Closed)
        <form method="post" action="/admin/crm/tickets/{{ $ticket->id() }}/replies" style="margin-top:12px">
            @csrf
            <div class="form-grid">
                <label class="full">{{ trans('crm::messages.reply_body') }} *
                    <textarea name="body" required minlength="2" maxlength="20000"></textarea>
                </label>
                <label style="flex-direction:row;align-items:center;gap:6px">
                    <input type="checkbox" name="internal" value="1" style="width:auto">
                    {{ trans('crm::messages.internal_note') }}
                </label>
            </div>
            <p style="margin-top:8px">
                <button class="btn" type="submit">{{ trans('crm::messages.reply') }}</button>
                <span class="muted">— پاسخ عمومی، تیکت را «منتظر مشتری» می‌کند (توقف SLA)</span>
            </p>
        </form>
    @endif
</div>
@endsection
