@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.monitoring'))

@section('content')
@php
    $instances = $data['instances'];
    $sla = $data['sla'];
    $fmt = '\Modules\Bpms\Domain\Services\MonitoringCalculator';
@endphp
<div class="topline">
    <h1>📊 {{ trans('bpms::messages.monitoring') }}</h1>
    <div class="spacer"></div>
    <form class="inline" method="get" action="/admin/bpms">
        <select name="key" onchange="this.form.submit()" style="width:auto">
            <option value="">{{ trans('bpms::messages.all_definitions') }}</option>
            @foreach($keys as $key => $name)
                <option value="{{ $key }}" {{ $activeKey === $key ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn ghost sm" href="/admin/bpms/reports/kpis.csv{{ $activeKey !== '' ? '?key=' . $activeKey : '' }}">⬇ {{ trans('bpms::messages.download_csv') }}</a>
</div>

<div class="stats">
    <div class="stat"><div class="num">{{ trans_number($instances['running'] ?? 0) }}</div><div class="muted">{{ trans('bpms::messages.active_instances') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($instances['completed'] ?? 0) }}</div><div class="muted">{{ trans('bpms::messages.completed_instances') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($instances['suspended'] ?? 0) }}</div><div class="muted">{{ trans('bpms::messages.suspended') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($instances['terminated'] ?? 0) }}</div><div class="muted">{{ trans('bpms::messages.terminated') }}</div></div>
    <div class="stat"><div class="num">{{ $fmt::formatDuration($data['avg_cycle_ms']) }}</div><div class="muted">{{ trans('bpms::messages.avg_cycle') }}</div></div>
    <div class="stat">
        <div class="num">{{ $sla['compliance'] === null ? '—' : trans_number((string) $sla['compliance']) . '٪' }}</div>
        <div class="muted">{{ trans('bpms::messages.sla_compliance') }} ({{ trans_number($sla['on_time']) }}/{{ trans_number($sla['with_due']) }})</div>
    </div>
</div>

<div class="grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card">
        <h2 style="font-size:.95rem;margin-bottom:8px">🐌 {{ trans('bpms::messages.bottlenecks') }}</h2>
        <table class="data">
            <thead><tr><th>state</th><th>{{ trans('bpms::messages.avg_cycle') }}</th><th>count</th></tr></thead>
            <tbody>
            @foreach($data['bottlenecks'] as $bn)
                <tr>
                    <td><code>{{ $bn['state'] }}</code></td>
                    <td>{{ $fmt::formatDuration((int) $bn['avg_ms']) }}</td>
                    <td>{{ trans_number((int) $bn['count']) }}</td>
                </tr>
            @endforeach
            @if(count($data['bottlenecks']) === 0)
                <tr><td colspan="3" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
            @endif
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:.95rem;margin-bottom:8px">👥 {{ trans('bpms::messages.workload') }}</h2>
        <table class="data">
            <thead><tr><th>{{ trans('bpms::messages.assignee') }}</th><th>باز</th><th>{{ trans('bpms::messages.overdue') }}</th><th>{{ trans('bpms::messages.escalated') }}</th></tr></thead>
            <tbody>
            @foreach($data['workload'] as $assignee => $load)
                <tr>
                    <td><code>{{ $assignee }}</code></td>
                    <td>{{ trans_number((int) $load['open']) }}</td>
                    <td>{{ (int) $load['overdue'] > 0 ? '🔴 ' . trans_number((int) $load['overdue']) : trans_number(0) }}</td>
                    <td>{{ trans_number((int) $load['escalated']) }}</td>
                </tr>
            @endforeach
            @if(count($data['workload']) === 0)
                <tr><td colspan="4" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">🕒 {{ trans('bpms::messages.recent_activity') }}</h2>
    <div class="timeline">
        @foreach($data['recent'] as $ev)
            <div class="ev {{ (string) ($ev['trigger'] ?? 'system') }}">
                <strong>{{ $ev['transition'] }}</strong>
                <span class="muted">{{ $ev['from_state'] ?? '—' }} ← {{ $ev['to_state'] }}</span>
                <span class="muted" style="float:left">{{ jalali($ev['occurred_at'] ?? null, 'Y/m/d H:i') }}</span>
            </div>
        @endforeach
        @if(count($data['recent']) === 0)
            <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
        @endif
    </div>
</div>
@endsection
