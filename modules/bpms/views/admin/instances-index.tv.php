@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.instances'))

@section('content')
<div class="topline">
    <h1>🚦 {{ trans('bpms::messages.instances') }}</h1>
    <div class="spacer"></div>
    @if(\Tavaan\Core\Facade\Facades\Gate::allows('bpms.instance.start'))
        <a class="btn" href="/admin/bpms/instances/create">+ {{ trans('bpms::messages.start_process') }}</a>
    @endif
</div>

<div class="card">
    <form method="get" action="/admin/bpms/instances" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="text" name="q" value="{{ $query }}" placeholder="{{ trans('bpms::messages.business_key') }}…" style="max-width:220px">
        <select name="status" style="width:auto">
            <option value="">—</option>
            @foreach(['running', 'completed', 'suspended', 'terminated'] as $st)
                <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ trans('bpms::messages.' . $st) }}</option>
            @endforeach
        </select>
        <button class="btn ghost" type="submit">{{ trans('bpms::messages.search') }}</button>
    </form>

    <table class="data">
        <thead>
        <tr><th>فرایند</th><th>{{ trans('bpms::messages.business_key') }}</th><th>{{ trans('bpms::messages.current_state') }}</th><th>{{ trans('bpms::messages.status') }}</th><th>{{ trans('bpms::messages.initiator') }}</th><th>آغاز</th></tr>
        </thead>
        <tbody>
        @foreach($instances as $instance)
            <tr>
                <td><a href="/admin/bpms/instances/{{ $instance->id() }}"><code>{{ $instance->definitionKey() }}</code></a></td>
                <td>{{ $instance->businessKey() ?? '—' }}</td>
                <td><code>{{ $instance->currentState() }}</code></td>
                <td>
                    @php $st = $instance->status()->value; @endphp
                    <span class="badge {{ $st === 'running' ? 'blue' : ($st === 'completed' ? 'green' : ($st === 'suspended' ? 'amber' : 'red')) }}">{{ trans('bpms::messages.' . $st) }}</span>
                </td>
                <td>{{ $instance->initiatorId() !== null ? ($initiators[(string) $instance->initiatorId()] ?? ('#' . trans_number($instance->initiatorId()))) : '—' }}</td>
                <td class="muted">{{ jalali($instance->startedAt(), 'Y/m/d H:i') }}</td>
            </tr>
        @endforeach
        @if(count($instances) === 0)
            <tr><td colspan="6" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>

    @include('bpms::partials.pagination', ['baseUrl' => '/admin/bpms/instances', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection
