@extends('crm::layouts.admin')

@section('title', 'داشبورد CRM')

@section('content')
<div class="topline">
    <h1>🤝 داشبورد CRM — {{ $me }}</h1>
</div>

<div class="stats">
    @if($contactsCount !== null)
        <div class="stat"><div class="num">{{ trans_number($contactsCount) }}</div><div class="muted">مخاطب</div></div>
    @endif
    @if($forecast !== null)
        <div class="stat"><div class="num">{{ trans_number($forecast['count']) }}</div><div class="muted">فرصت باز</div></div>
        <div class="stat"><div class="num">{{ trans_number($forecast['weighted']) }}</div><div class="muted">پیش‌بینی وزنی (ریال)</div></div>
    @endif
    @if($ticketStats !== null)
        <div class="stat"><div class="num">{{ trans_number($ticketStats['open'] + $ticketStats['pending']) }}</div><div class="muted">تیکت فعال</div></div>
        <div class="stat"><div class="num">{{ trans_number($ticketStats['escalated']) }}</div><div class="muted">escalate شده</div></div>
    @endif
</div>

@if(count($newLeads) > 0)
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🎯 سرنخ‌های جدید</h3>
    <table class="data">
        <thead><tr><th>عنوان</th><th>ارزش</th><th>امتیاز</th><th></th></tr></thead>
        <tbody>
        @foreach($newLeads as $lead)
            <tr>
                <td><a href="/admin/crm/leads/{{ $lead->id() }}">{{ $lead->title() }}</a></td>
                <td>{{ trans_number($lead->valueMinor()) }} ریال</td>
                <td>
                    <span class="score-pill {{ $lead->score() >= 70 ? 'score-hot' : ($lead->score() >= 40 ? 'score-warm' : 'score-cold') }}">
                        {{ trans_number($lead->score()) }}
                    </span>
                </td>
                <td><a class="btn sm ghost" href="/admin/crm/leads/{{ $lead->id() }}">بررسی</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if(count($myTasks) > 0)
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">☑️ کارهای من</h3>
    @foreach($myTasks as $task)
        <div style="display:flex;gap:8px;align-items:center;padding:5px 0;border-bottom:1px solid #f1f5f9">
            <span>{{ $task->isOverdue() ? '🔴' : '🕐' }}</span>
            <span style="flex:1">
                {{ $task->subject() }}
                <span class="muted">سررسید: {{ jalali($task->dueAt(), 'Y/m/d H:i') }}</span>
            </span>
            <form class="inline" method="post" action="/admin/crm/activities/{{ $task->id() }}/complete">
                @csrf
                <button class="btn sm success" type="submit">تکمیل</button>
            </form>
        </div>
    @endforeach
</div>
@endif

@if(count($stageCounts) > 0)
<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">📈 فرصت‌ها بر اساس مرحله</h3>
    <table class="data">
        <tbody>
        @foreach(\Modules\Crm\Domain\Enums\OpportunityStage::cases() as $stage)
            <tr>
                <td><a href="/admin/crm/opportunities?stage={{ $stage->value }}">{{ $stage->labelFa() }}</a></td>
                <td>{{ trans_number($stageCounts[$stage->value] ?? 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
