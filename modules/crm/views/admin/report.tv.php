@extends('crm::layouts.admin')

@section('title', 'گزارش‌های CRM')

@php
    $pipeline = $overview['pipeline'];
    $winLoss = $overview['win_loss'];
    $tickets = $overview['tickets'];
    $leadSources = $overview['lead_sources'];
    $agents = $overview['agents'];
    $revenue = $overview['revenue'];
@endphp

@section('content')
<div class="topline">
    <h1>📊 گزارش‌های CRM</h1>
    <span class="spacer"></span>
    <a class="btn ghost" href="/admin/crm/reports/pipeline.csv">{{ trans('crm::messages.report_download_csv') }}</a>
</div>

<div class="stats">
    <div class="stat"><div class="num">{{ trans_number($pipeline['count']) }}</div><div class="muted">فرصت باز</div></div>
    <div class="stat"><div class="num">{{ trans_number($pipeline['total_value']) }}</div><div class="muted">ارزش pipeline (ریال)</div></div>
    <div class="stat"><div class="num">{{ trans_number($pipeline['weighted']) }}</div><div class="muted">{{ trans('crm::messages.weighted_forecast') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number((int) $winLoss['win_rate']) }}٪</div><div class="muted">{{ trans('crm::messages.report_win_rate') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number((int) $tickets['sla_compliance']) }}٪</div><div class="muted">{{ trans('crm::messages.report_sla_compliance') }}</div></div>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_pipeline') }}</h3>
    <table class="data">
        <thead><tr><th>مرحله</th><th>تعداد</th><th>ارزش (ریال)</th><th>وزنی (ریال)</th></tr></thead>
        <tbody>
        @forelse($pipeline['by_stage'] as $stage => $row)
            <tr>
                <td><span class="badge blue">{{ \Modules\Crm\Domain\Enums\OpportunityStage::tryFromString((string) $stage)->labelFa() }}</span></td>
                <td>{{ trans_number($row['count']) }}</td>
                <td>{{ trans_number($row['value']) }}</td>
                <td>{{ trans_number($row['weighted']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">فرصت بازی نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:16px">
    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_win_loss') }}</h3>
        <p>
            بردها: <strong>{{ trans_number($winLoss['won']) }}</strong> •
            باخت‌ها: <strong>{{ trans_number($winLoss['lost']) }}</strong> •
            باز: <strong>{{ trans_number($winLoss['open']) }}</strong>
            ({{ trans_number($winLoss['open_value']) }} ریال)
        </p>
    </div>

    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_tickets') }}</h3>
        <p>
            کل: <strong>{{ trans_number($tickets['total']) }}</strong> •
            باز: <strong>{{ trans_number($tickets['open']) }}</strong> •
            منتظر مشتری: <strong>{{ trans_number($tickets['pending']) }}</strong> •
            حل‌شده: <strong>{{ trans_number($tickets['resolved']) }}</strong> •
            escalate: <strong>{{ trans_number($tickets['escalated']) }}</strong><br>
            {{ trans('crm::messages.report_avg_resolution') }}: <strong>{{ trans_number((int) $tickets['avg_resolution_minutes']) }}</strong>
        </p>
    </div>

    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_lead_sources') }}</h3>
        <table class="data">
            <thead><tr><th>منبع</th><th>کل</th><th>{{ trans('crm::messages.qualified') }}</th><th>{{ trans('crm::messages.converted') }}</th><th>ازدست‌رفته</th></tr></thead>
            <tbody>
            @forelse($leadSources as $source => $stat)
                <tr>
                    <td><span class="badge">{{ $source }}</span></td>
                    <td>{{ trans_number($stat['total']) }}</td>
                    <td>{{ trans_number($stat['qualified']) }}</td>
                    <td>{{ trans_number($stat['converted']) }}</td>
                    <td>{{ trans_number($stat['lost']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_agents') }}</h3>
        <table class="data">
            <thead><tr><th>{{ trans('crm::messages.agent') }}</th><th>{{ trans('crm::messages.won_count') }}</th><th>{{ trans('crm::messages.won_value') }}</th><th>{{ trans('crm::messages.tickets_assigned') }}</th><th>{{ trans('crm::messages.tickets_resolved') }}</th></tr></thead>
            <tbody>
            @forelse($agents as $agent)
                <tr>
                    <td>{{ $agentNames[$agent['owner_id']] ?? ('#' . $agent['owner_id']) }}</td>
                    <td>{{ trans_number($agent['won_count']) }}</td>
                    <td>{{ trans_number($agent['won_value']) }}</td>
                    <td>{{ trans_number($agent['tickets_assigned']) }}</td>
                    <td>{{ trans_number($agent['tickets_resolved']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">—</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.report_revenue') }}</h3>
        <table class="data">
            <tbody>
            @foreach($revenue as $month => $value)
                <tr>
                    <td dir="ltr" class="muted">{{ $month }}</td>
                    <td>{{ trans_number($value) }} ریال</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
