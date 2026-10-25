@extends('crm::layouts.admin')

@section('title', 'تیکت‌ها')

@section('content')
<div class="topline">
    <h1>🎫 تیکت‌ها</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/tickets?escalated=1">⚠ فقط escalateشده‌ها</a>
</div>

<div class="card">
    <form method="get" action="/admin/crm/tickets" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <label style="flex:1;min-width:180px;font-size:.85rem">{{ trans('crm::messages.search') }}
            <input type="text" name="q" value="{{ $query }}" placeholder="عنوان یا شمارهٔ تیکت">
        </label>
        <label style="font-size:.85rem">{{ trans('crm::messages.status') }}
            <select name="status">
                <option value="">همه</option>
                @foreach(\Modules\Crm\Domain\Enums\TicketStatus::cases() as $s)
                    <option value="{{ $s->value }}" {{ $statusFilter === $s->value ? 'selected' : '' }}>{{ $s->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <label style="font-size:.85rem">{{ trans('crm::messages.priority') }}
            <select name="priority">
                <option value="">همه</option>
                @foreach(\Modules\Crm\Domain\Enums\TicketPriority::cases() as $p)
                    <option value="{{ $p->value }}" {{ $priorityFilter === $p->value ? 'selected' : '' }}>{{ $p->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <label style="font-size:.82rem;display:flex;align-items:center;gap:4px">
            <input type="checkbox" name="escalated" value="1" {{ $escalatedOnly ? 'checked' : '' }} style="width:auto"> escalateشده
        </label>
        <button class="btn ghost" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead><tr><th>شماره</th><th>عنوان</th><th>اولویت</th><th>وضعیت</th><th>کارشناس</th><th>SLA</th><th></th></tr></thead>
        <tbody>
        @forelse($tickets as $ticket)
            <tr>
                <td dir="ltr" class="muted">{{ $ticket->ticketNumber() }}</td>
                <td><a href="/admin/crm/tickets/{{ $ticket->id() }}"><strong>{{ $ticket->subject() }}</strong></a></td>
                <td>
                    @if($ticket->priority() === \Modules\Crm\Domain\Enums\TicketPriority::Urgent)<span class="badge red">فوری</span>
                    @elseif($ticket->priority() === \Modules\Crm\Domain\Enums\TicketPriority::High)<span class="badge amber">زیاد</span>
                    @else<span class="badge">{{ $ticket->priority()->labelFa() }}</span>@endif
                </td>
                <td>
                    @if($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Open)<span class="badge blue">باز</span>
                    @elseif($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Pending)<span class="badge amber">منتظر مشتری</span>
                    @elseif($ticket->status() === \Modules\Crm\Domain\Enums\TicketStatus::Resolved)<span class="badge green">حل‌شده</span>
                    @else<span class="badge">بسته</span>@endif
                    @if($ticket->isEscalated())<span class="badge red">⚠</span>@endif
                </td>
                <td>{{ $ticket->assignedTo() !== null ? ($assignees[$ticket->assignedTo()] ?? '—') : '—' }}</td>
                <td class="muted">
                    @if($ticket->slaDueAt() !== null)
                        @php $left = $ticket->slaMinutesLeft(); @endphp
                        @if($ticket->status()->slaRunning() && $left !== null && $left < 0)
                            <span class="badge red">{{ trans('crm::messages.sla_breached') }}</span>
                        @elseif($left !== null)
                            {{ trans_number(max(0, $left)) }} دقیقه
                        @endif
                    @endif
                </td>
                <td><a class="btn sm ghost" href="/admin/crm/tickets/{{ $ticket->id() }}">مشاهده</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ trans('crm::messages.no_results') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @include('crm::partials.pagination', ['baseUrl' => '/admin/crm/tickets', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection
