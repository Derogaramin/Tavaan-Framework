@extends('bpms::layouts.admin')

@section('title', 'نمونهٔ ' . $instance->id())

@section('content')
@php
    $st = $instance->status()->value;
    $fmt = '\Modules\Bpms\Domain\Services\MonitoringCalculator';
@endphp
<div class="topline">
    <h1>🚦 <code>{{ $instance->definitionKey() }}</code></h1>
    <span class="badge {{ $st === 'running' ? 'blue' : ($st === 'completed' ? 'green' : ($st === 'suspended' ? 'amber' : 'red')) }}">{{ trans('bpms::messages.' . $st) }}</span>
    <div class="spacer"></div>
    @if($canManage && $st === 'running')
        <form class="inline" method="post" action="/admin/bpms/instances/{{ $instance->id() }}/suspend">
            @csrf
            <button class="btn sm warn" type="submit">{{ trans('bpms::messages.suspend') }}</button>
        </form>
    @endif
    @if($canManage && $st === 'suspended')
        <form class="inline" method="post" action="/admin/bpms/instances/{{ $instance->id() }}/resume">
            @csrf
            <button class="btn sm success" type="submit">{{ trans('bpms::messages.resume') }}</button>
        </form>
    @endif
    @if($canTerminate && ($st === 'running' || $st === 'suspended'))
        <form class="inline" method="post" action="/admin/bpms/instances/{{ $instance->id() }}/terminate"
              onsubmit="return confirm('نمونه به‌طور اجباری پایان یابد؟ کارهای باز لغو می‌شوند.')">
            @csrf
            <input type="hidden" name="reason" value="admin">
            <button class="btn sm danger" type="submit">{{ trans('bpms::messages.terminate') }}</button>
        </form>
    @endif
</div>

<div class="card">
    <dl class="kv">
        <dt>شناسه</dt><dd><code>{{ $instance->id() }}</code></dd>
        <dt>تعریف</dt><dd>
            @if($definition !== null)
                <a href="/admin/bpms/definitions/{{ $definition->id() }}">{{ $definition->name() }} (v{{ trans_number($definition->version()) }})</a>
            @else—@endif
        </dd>
        <dt>{{ trans('bpms::messages.business_key') }}</dt><dd>{{ $instance->businessKey() ?? '—' }}</dd>
        <dt>{{ trans('bpms::messages.initiator') }}</dt><dd>{{ $initiatorName }}</dd>
        <dt>{{ trans('bpms::messages.current_state') }}</dt><dd><code>{{ $instance->currentState() }}</code> @if(count($instance->tokens()) > 1)<span class="badge amber">{{ trans_number(count($instance->tokens())) }} توکن موازی</span>@endif</dd>
        <dt>آغاز</dt><dd>{{ jalali($instance->startedAt(), 'Y/m/d H:i:s') }}</dd>
        @if($instance->completedAt() !== null)
            <dt>پایان</dt><dd>{{ jalali($instance->completedAt(), 'Y/m/d H:i:s') }}</dd>
        @endif
        @if($instance->workflowInstanceId() !== null)
            <dt>mirror</dt><dd class="muted"><code>{{ $instance->workflowInstanceId() }}</code></dd>
        @endif
    </dl>
</div>

@if($svg !== '')
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">🗺 {{ trans('bpms::messages.diagram') }} <span class="muted">(سبز = توکن فعال)</span></h2>
    <div class="diagram-wrap">{!! $svg !!}</div>
</div>
@endif

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">{{ trans('bpms::messages.variables') }}</h2>
    @if(count($variables) > 0)
        <dl class="kv">
            @foreach($variables as $key => $value)
                <dt><code>{{ $key }}</code></dt>
                <dd>{{ is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
            @endforeach
        </dl>
    @else
        <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
    @endif
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">☑️ {{ trans('bpms::messages.tasks') }}</h2>
    <table class="data">
        <thead><tr><th>عنوان</th><th>state</th><th>{{ trans('bpms::messages.assignee') }}</th><th>{{ trans('bpms::messages.status') }}</th><th>{{ trans('bpms::messages.due_at') }}</th></tr></thead>
        <tbody>
        @foreach($tasks as $task)
            <tr>
                <td><a href="/bpms/tasks/{{ $task->id() }}">{{ $task->title() }}</a></td>
                <td><code>{{ $task->stateId() }}</code></td>
                <td>{{ $task->assigneeType()->value }}{{ $task->assigneeId() !== null ? ':' . $task->assigneeId() : '' }}</td>
                <td>
                    @php $tst = $task->status()->value; @endphp
                    <span class="badge {{ $tst === 'completed' ? 'green' : ($tst === 'canceled' ? 'gray' : ($tst === 'claimed' ? 'blue' : 'amber')) }}">{{ trans('bpms::messages.task_' . $tst) }}</span>
                    @if($task->isEscalated())<span class="badge red">{{ trans('bpms::messages.escalated') }}</span>@endif
                </td>
                <td class="muted">{{ $task->dueAt() !== null ? jalali($task->dueAt(), 'Y/m/d H:i') : '—' }}</td>
            </tr>
        @endforeach
        @if(count($tasks) === 0)
            <tr><td colspan="5" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">🕒 {{ trans('bpms::messages.timeline') }}</h2>
    <div class="timeline">
        @foreach($history as $entry)
            <div class="ev {{ $entry->trigger }}">
                <strong>{{ $entry->transition }}</strong>
                <span class="muted">{{ $entry->fromState ?? '—' }} ← {{ $entry->toState }}</span>
                @if($entry->durationMs !== null)
                    <span class="badge gray">{{ $fmt::formatDuration($entry->durationMs) }}</span>
                @endif
                <span class="muted" style="float:left">{{ jalali($entry->occurredAt, 'Y/m/d H:i:s') }} • {{ $entry->trigger }}{{ $entry->actorId !== null ? ' • #' . trans_number($entry->actorId) : '' }}</span>
            </div>
        @endforeach
        @if(count($history) === 0)
            <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
        @endif
    </div>
</div>
@endsection
