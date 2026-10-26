@extends('bpms::layouts.web')

@section('title', $task->title())

@section('content')
@php
    $tst = $task->status()->value;
@endphp
<div class="card">
    <h1>☑️ {{ $task->title() }}</h1>
    <p class="muted">
        فرایند:
        @if($instance !== null)<a href="/bpms/instances/{{ $instance->id() }}"><code>{{ $instance->definitionKey() }}</code></a>@endif
        • state: <code>{{ $task->stateId() }}</code>
        • <span class="badge {{ $tst === 'completed' ? 'green' : ($tst === 'canceled' ? 'gray' : ($tst === 'claimed' ? 'blue' : 'amber')) }}">{{ trans('bpms::messages.task_' . $tst) }}</span>
        @if($task->dueAt() !== null)
            • {{ trans('bpms::messages.due_at') }}: {{ jalali($task->dueAt(), 'Y/m/d H:i') }}
            @if($task->isOverdue(\Tavaan\Support\Time\Clock::now()))<span class="badge red">{{ trans('bpms::messages.overdue') }}</span>@endif
        @endif
        @if($task->isEscalated())<span class="badge red">{{ trans('bpms::messages.escalated') }}</span>@endif
    </p>
</div>

@if($task->status()->isOpen())
<div class="card">
    <h2>فرم کار</h2>

    @if($task->status()->value === 'pending' && $canClaim)
        <form method="post" action="/bpms/tasks/{{ $task->id() }}/claim" style="margin-bottom:12px">
            @csrf
            <button class="btn" type="submit">✋ {{ trans('bpms::messages.claim') }}</button>
            <span class="help">پس از تصاحب، کار در فهرست شما قرار می‌گیرد.</span>
        </form>
    @endif

    <form method="post" action="/bpms/tasks/{{ $task->id() }}/complete">
        @csrf

        @if($form !== null)
            @include('bpms::partials.dynamic-form', ['form' => $form, 'values' => $task->formData(), 'errors' => session('errors')])
        @else
            <div class="help" style="margin-bottom:12px">این کار فرمی ندارد — در صورت نیاز یادداشت بنویسید و تکمیل کنید.</div>
        @endif

        <div class="form-row">
            <label>
                <span>{{ trans('bpms::messages.comment') }}</span>
                <textarea name="comment" rows="2" maxlength="1000"></textarea>
            </label>
        </div>

        @if($canComplete)
            <button class="btn success" type="submit">✔ {{ trans('bpms::messages.complete') }}</button>
        @endif
    </form>

    @if($canDelegate)
        <details style="margin-top:14px">
            <summary class="muted" style="cursor:pointer">↪ {{ trans('bpms::messages.delegate') }}</summary>
            <form method="post" action="/bpms/tasks/{{ $task->id() }}/delegate" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                @csrf
                <select name="assignee_type" style="width:auto">
                    <option value="user">user</option>
                    <option value="role">role</option>
                </select>
                <input type="text" name="assignee_id" placeholder="شناسهٔ کاربر یا کلید نقش" style="max-width:220px" required>
                <button class="btn ghost" type="submit">{{ trans('bpms::messages.delegate') }}</button>
            </form>
        </details>
    @endif
</div>
@else
<div class="card">
    <h2>دادهٔ ثبت‌شده</h2>
    @if(count($task->formData()) > 0)
        <table class="data">
            @foreach($task->formData() as $key => $value)
                <tr><th style="width:200px"><code>{{ $key }}</code></th><td>{{ is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td></tr>
            @endforeach
        </table>
    @else
        <div class="muted">—</div>
    @endif
    @if($task->comment() !== null)
        <p class="muted" style="margin-top:8px">📝 {{ $task->comment() }}</p>
    @endif
</div>
@endif
@endsection
