@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.definitions'))

@section('content')
<div class="topline">
    <h1>🧩 {{ trans('bpms::messages.definitions') }}</h1>
    <div class="spacer"></div>
    @if(\Tavaan\Core\Facade\Facades\Gate::allows('bpms.definition.manage'))
        <a class="btn" href="/admin/bpms/definitions/create">+ {{ trans('bpms::messages.new_definition') }}</a>
    @endif
</div>

<div class="card">
    <form method="get" action="/admin/bpms/definitions" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="text" name="q" value="{{ $query }}" placeholder="{{ trans('bpms::messages.search') }}…" style="max-width:260px">
        <select name="status" style="width:auto">
            <option value="">—</option>
            @foreach(['draft', 'published', 'retired'] as $st)
                <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ trans('bpms::messages.' . $st) }}</option>
            @endforeach
        </select>
        <button class="btn ghost" type="submit">{{ trans('bpms::messages.search') }}</button>
    </form>

    <table class="data">
        <thead>
        <tr><th>{{ trans('bpms::messages.definitions') }}</th><th>کلید</th><th>{{ trans('bpms::messages.version') }}</th><th>{{ trans('bpms::messages.status') }}</th><th></th></tr>
        </thead>
        <tbody>
        @foreach($definitions as $definition)
            <tr>
                <td><a href="/admin/bpms/definitions/{{ $definition->id() }}">{{ $definition->name() }}</a>
                    @if($definition->description() !== null)<div class="muted">{{ $definition->description() }}</div>@endif
                </td>
                <td><code>{{ $definition->key() }}</code></td>
                <td>{{ trans_number($definition->version()) }}</td>
                <td>
                    <span class="badge {{ $definition->status()->value === 'published' ? 'green' : ($definition->status()->value === 'draft' ? 'amber' : 'gray') }}">{{ trans('bpms::messages.' . $definition->status()->value) }}</span>
                    @if($definition->isActive())<span class="badge blue">{{ trans('bpms::messages.active') }}</span>@endif
                </td>
                <td>
                    <a class="btn sm ghost" href="/admin/bpms/definitions/{{ $definition->id() }}">مشاهده</a>
                    @if($definition->status()->isEditable() && \Tavaan\Core\Facade\Facades\Gate::allows('bpms.definition.manage'))
                        <a class="btn sm" href="/admin/bpms/definitions/{{ $definition->id() }}/edit">ویرایش</a>
                    @endif
                </td>
            </tr>
        @endforeach
        @if(count($definitions) === 0)
            <tr><td colspan="5" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>

    @include('bpms::partials.pagination', ['baseUrl' => '/admin/bpms/definitions', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection
