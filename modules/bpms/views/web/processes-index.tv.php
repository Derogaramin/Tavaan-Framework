@extends('bpms::layouts.web')

@section('title', trans('bpms::messages.processes'))

@section('content')
<div class="card">
    <h1>🚀 {{ trans('bpms::messages.processes') }}</h1>
    <p class="muted" style="margin-bottom:12px">فرایند مورد نظر را برای آغاز انتخاب کنید.</p>

    @if(count($definitions) > 0)
        <table class="data">
            <thead><tr><th>فرایند</th><th>توضیحات</th><th></th></tr></thead>
            <tbody>
            @foreach($definitions as $definition)
                <tr>
                    <td><strong>{{ $definition->name() }}</strong> <span class="muted">v{{ trans_number($definition->version()) }}</span></td>
                    <td class="muted">{{ $definition->description() ?? '—' }}</td>
                    <td><a class="btn sm" href="/bpms/processes/{{ $definition->key() }}/start">{{ trans('bpms::messages.start_process') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
    @endif
</div>
@endsection
