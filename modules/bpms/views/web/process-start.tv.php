@extends('bpms::layouts.web')

@section('title', trans('bpms::messages.start_process') . ' — ' . $definition->name())

@section('content')
<div class="card">
    <h1>🚀 {{ $definition->name() }}</h1>
    @if($definition->description() !== null)
        <p class="muted">{{ $definition->description() }}</p>
    @endif
</div>

@if($svg !== '')
<div class="card">
    <h2>🗺 {{ trans('bpms::messages.diagram') }}</h2>
    <div class="diagram-wrap">{!! $svg !!}</div>
</div>
@endif

<div class="card">
    <form method="post" action="/bpms/processes/{{ $definition->key() }}/start">
        @csrf
        <div class="form-row">
            <label>
                <span>{{ trans('bpms::messages.business_key') }} (اختیاری)</span>
                <input type="text" name="business_key" maxlength="100" placeholder="مثلاً REQ-1405-001">
            </label>
        </div>
        <div class="form-row">
            <label>
                <span>{{ trans('bpms::messages.variables') }} — اختیاری</span>
                <textarea class="code" name="variables" rows="3" placeholder='{"days": 5}'></textarea>
                <span class="help">معمولاً فرم اولین کار، داده‌ها را از شما می‌گیرد — این فیلد برای موارد پیشرفته است.</span>
            </label>
        </div>
        <button class="btn success" type="submit">{{ trans('bpms::messages.start_process') }}</button>
        <a class="btn ghost" href="/bpms/processes">{{ trans('bpms::messages.cancel') }}</a>
    </form>
</div>
@endsection
