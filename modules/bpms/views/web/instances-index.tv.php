@extends('bpms::layouts.web')

@section('title', trans('bpms::messages.instances'))

@section('content')
<div class="card">
    <h1>🚦 نمونه‌های فرایند</h1>
    <form method="get" action="/bpms/instances" style="display:flex;gap:8px;margin-bottom:12px">
        <select name="status" style="width:auto">
            <option value="">—</option>
            @foreach(['running', 'completed', 'suspended', 'terminated'] as $st)
                <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ trans('bpms::messages.' . $st) }}</option>
            @endforeach
        </select>
        <button class="btn ghost sm" type="submit">فیلتر</button>
    </form>

    <table class="data">
        <thead><tr><th>فرایند</th><th>{{ trans('bpms::messages.business_key') }}</th><th>{{ trans('bpms::messages.current_state') }}</th><th>{{ trans('bpms::messages.status') }}</th><th>آغاز</th></tr></thead>
        <tbody>
        @foreach($instances as $instance)
            @php $st = $instance->status()->value; @endphp
            <tr>
                <td><a href="/bpms/instances/{{ $instance->id() }}"><code>{{ $instance->definitionKey() }}</code></a></td>
                <td>{{ $instance->businessKey() ?? '—' }}</td>
                <td><code>{{ $instance->currentState() }}</code></td>
                <td><span class="badge {{ $st === 'running' ? 'blue' : ($st === 'completed' ? 'green' : ($st === 'suspended' ? 'amber' : 'red')) }}">{{ trans('bpms::messages.' . $st) }}</span></td>
                <td class="muted">{{ jalali($instance->startedAt(), 'Y/m/d H:i') }}</td>
            </tr>
        @endforeach
        @if(count($instances) === 0)
            <tr><td colspan="5" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>

    @include('bpms::partials.pagination', ['baseUrl' => '/bpms/instances', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection
