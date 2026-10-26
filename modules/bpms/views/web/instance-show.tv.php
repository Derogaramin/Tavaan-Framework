@extends('bpms::layouts.web')

@section('title', 'نمونهٔ ' . $instance->definitionKey())

@section('content')
@php $st = $instance->status()->value; @endphp
<div class="card">
    <h1>🚦 <code>{{ $instance->definitionKey() }}</code>
        <span class="badge {{ $st === 'running' ? 'blue' : ($st === 'completed' ? 'green' : ($st === 'suspended' ? 'amber' : 'red')) }}">{{ trans('bpms::messages.' . $st) }}</span>
    </h1>
    <p class="muted">
        {{ trans('bpms::messages.current_state') }}: <code>{{ $instance->currentState() }}</code>
        • آغاز: {{ jalali($instance->startedAt(), 'Y/m/d H:i') }}
        @if($instance->businessKey() !== null)• {{ trans('bpms::messages.business_key') }}: {{ $instance->businessKey() }}@endif
    </p>
</div>

@if($svg !== '')
<div class="card">
    <h2>🗺 {{ trans('bpms::messages.diagram') }}</h2>
    <div class="diagram-wrap">{!! $svg !!}</div>
</div>
@endif

@if(count($tasks) > 0)
<div class="card">
    <h2>☑️ کارها</h2>
    <table class="data">
        <tbody>
        @foreach($tasks as $task)
            @php $tst = $task->status()->value; @endphp
            <tr>
                <td><a href="/bpms/tasks/{{ $task->id() }}">{{ $task->title() }}</a></td>
                <td><span class="badge {{ $tst === 'completed' ? 'green' : ($tst === 'canceled' ? 'gray' : ($tst === 'claimed' ? 'blue' : 'amber')) }}">{{ trans('bpms::messages.task_' . $tst) }}</span></td>
                <td class="muted">{{ $task->dueAt() !== null ? jalali($task->dueAt(), 'Y/m/d H:i') : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="card">
    <h2>🕒 {{ trans('bpms::messages.timeline') }}</h2>
    <div class="timeline">
        @foreach($history as $entry)
            <div class="ev">
                <strong>{{ $entry->transition }}</strong>
                <span class="muted">{{ $entry->fromState ?? '—' }} ← {{ $entry->toState }}</span>
                <span class="muted" style="float:left">{{ jalali($entry->occurredAt, 'Y/m/d H:i') }}</span>
            </div>
        @endforeach
        @if(count($history) === 0)
            <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
        @endif
    </div>
</div>
@endsection
