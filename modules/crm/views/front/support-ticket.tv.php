@extends('crm::layouts.crm')

@section('pageTitle', trans('crm::messages.ticket_number') . ' ' . $ticket->ticketNumber())

@section('content')
<div class="crm-card">
    <p class="muted">{{ trans('crm::messages.ticket_number') }}: <strong dir="ltr">{{ $ticket->ticketNumber() }}</strong></p>
    <h1>{{ $ticket->subject() }}</h1>
    <p>
        <span class="badge {{ $ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Open ? 'amber' : ($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Closed ? 'red' : 'green') }}">
            {{ $ticket->status()->labelFa() }}
        </span>
        <span class="badge">{{ $ticket->priority()->labelFa() }}</span>
        @if($ticket->isEscalated())<span class="badge red">{{ trans('crm::messages.escalated') }}</span>@endif
    </p>
    <p>{{ $ticket->body() }}</p>
    <p class="muted">{{ jalali($ticket->createdAt(), 'Y/m/d H:i') }}</p>
</div>

<div class="crm-card">
    <h2>{{ trans('crm::messages.replies') }}</h2>
    @forelse($replies as $reply)
        <div class="reply {{ $reply->isAgent() ? 'agent' : '' }}">
            <strong>{{ $reply->isAgent() ? '🛠 پشتیبانی' : '👤 شما' }}</strong>
            <span class="muted">{{ jalali($reply->createdAt(), 'Y/m/d H:i') }}</span>
            <p>{{ $reply->body() }}</p>
        </div>
    @empty
        <p class="muted">—</p>
    @endforelse

    @if($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Resolved)
        <p class="muted">{{ trans('crm::messages.support_resolved_note') }}</p>
    @endif
</div>

@if($ticket->status() !== \Modules\Crm\Domain\Enums\TicketStatus::Closed)
<div class="crm-card">
    <h2>{{ trans('crm::messages.support_reply') }}</h2>
    <form method="post" action="/crm/support/{{ $ticket->ticketNumber() }}/replies" class="crm-form">
        @csrf
        <div class="field">
            <textarea name="body" required minlength="2" maxlength="20000" placeholder="{{ trans('crm::messages.support_reply') }}"></textarea>
        </div>
        <button class="crm-btn" type="submit">{{ trans('crm::messages.support_reply_submit') }}</button>
    </form>
</div>
@else
<div class="crm-card"><p class="muted">{{ trans('crm::messages.support_closed_note') }}</p></div>
@endif
@endsection
