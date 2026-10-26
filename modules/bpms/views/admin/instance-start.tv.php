@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.start_process'))

@section('content')
<div class="topline">
    <h1>🚀 {{ trans('bpms::messages.start_process') }}</h1>
</div>

<div class="card">
    <form method="post" action="/admin/bpms/instances">
        @csrf
        <div class="form-grid">
            <label>
                <span>فرایند <span class="req">*</span></span>
                <select name="definition_key" required>
                    <option value="">—</option>
                    @foreach($definitions as $definition)
                        <option value="{{ $definition->key() }}">{{ $definition->name() }} (v{{ trans_number($definition->version()) }})</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>{{ trans('bpms::messages.business_key') }}</span>
                <input type="text" name="business_key" maxlength="100" placeholder="مثلاً PO-1405-001">
            </label>
            <label class="full">
                <span>{{ trans('bpms::messages.variables') }} — اختیاری</span>
                <textarea class="code" name="variables" style="min-height:120px" placeholder='{"days": 5}'></textarea>
            </label>
        </div>
        <div style="margin-top:12px">
            <button class="btn success" type="submit" {{ count($definitions) === 0 ? 'disabled' : '' }}>{{ trans('bpms::messages.start_process') }}</button>
            <a class="btn ghost" href="/admin/bpms/instances">{{ trans('bpms::messages.cancel') }}</a>
        </div>
        @if(count($definitions) === 0)
            <div class="help" style="margin-top:8px">تعریف فعالی وجود ندارد — ابتدا فرایندی را منتشر کنید.</div>
        @endif
    </form>
</div>
@endsection
