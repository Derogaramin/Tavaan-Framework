@extends('bpms::layouts.admin')

@section('title', $definition === null ? trans('bpms::messages.new_definition') : trans('bpms::messages.edit_definition'))

@section('content')
<div class="topline">
    <h1>🧩 {{ $definition === null ? trans('bpms::messages.new_definition') : trans('bpms::messages.edit_definition') }}</h1>
    <div class="spacer"></div>
    @if($definition !== null)
        <a class="btn ghost sm" href="/admin/bpms/definitions/{{ $definition->id() }}">{{ trans('bpms::messages.back') }}</a>
    @endif
</div>

<div class="card">
    <form method="post" action="{{ $definition === null ? '/admin/bpms/definitions' : '/admin/bpms/definitions/' . $definition->id() }}">
        @csrf
        <div class="form-grid">
            <label>
                <span>کلید فرایند <span class="req">*</span></span>
                <input type="text" name="key" value="{{ $definition?->key() ?? '' }}" {{ $definition !== null ? 'readonly' : '' }}
                       placeholder="leave-request" style="direction:ltr" required>
                <span class="help">فقط حروف کوچک، رقم، - و _ — پس از ساخت تغییر نمی‌کند.</span>
            </label>
            <label>
                <span>نام فرایند <span class="req">*</span></span>
                <input type="text" name="name" value="{{ $definition?->name() ?? '' }}" required maxlength="190">
            </label>
            <label class="full">
                <span>توضیحات</span>
                <input type="text" name="description" value="{{ $definition?->description() ?? '' }}" maxlength="500">
            </label>
            <label class="full">
                <span>تعریف فرایند (JSON — elements/timers) <span class="req">*</span></span>
                <textarea class="code" name="definition" required>{{ $definitionJson }}</textarea>
            </label>
            <label class="full">
                <span>فرم‌ها (JSON — کلید: schema) </span>
                <textarea class="code" name="form_schema" style="min-height:200px">{{ $formsJson }}</textarea>
            </label>
        </div>
        <div style="margin-top:12px">
            <button class="btn success" type="submit">{{ trans('bpms::messages.save') }}</button>
            <a class="btn ghost" href="/admin/bpms/definitions">{{ trans('bpms::messages.cancel') }}</a>
        </div>
    </form>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">📖 راهنمای سریع JSON</h2>
    <div class="help" style="direction:ltr;text-align:left;line-height:2">
        elements: <code>startEvent</code> (next) • <code>userTask</code> (assignee: <code>initiator</code> | <code>user:ID</code> | <code>role:KEY</code>, form, due: <code>P2D</code>) •
        <code>serviceTask</code> (action — ثبت‌شده) • <code>exclusiveGateway</code> (conditions: expr/default → next) •
        <code>parallelGateway</code> (fork: [...] یا join) • <code>endEvent</code><br>
        expr: فقط <code>variables.x &gt; 5</code>, <code>==</code>, <code>&amp;&amp;</code>, <code>||</code>, <code>!</code> — بدون تابع (امن).<br>
        timers: <code>{"on": "task_id", "after": "P2D", "action": "escalate"}</code>
    </div>
</div>
@endsection
