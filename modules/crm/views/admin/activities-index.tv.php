@extends('crm::layouts.admin')

@section('title', 'فعالیت‌ها')

@section('content')
<div class="topline">
    <h1>☑️ فعالیت‌ها — {{ $userName }}</h1>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">{{ trans('crm::messages.my_tasks') }}</h3>
    @forelse($tasks as $task)
        <div style="display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <span>{{ $task->isOverdue() ? '🔴' : '🕐' }}</span>
            <span style="flex:1">
                <strong>{{ $task->subject() }}</strong>
                @if($task->body() !== null)<br><span class="muted">{{ $task->body() }}</span>@endif
                <br><span class="muted">سررسید: {{ jalali($task->dueAt(), 'Y/m/d H:i') }}</span>
            </span>
            <form class="inline" method="post" action="/admin/crm/activities/{{ $task->id() }}/complete">
                @csrf
                <button class="btn sm success" type="submit">{{ trans('crm::messages.complete') }}</button>
            </form>
        </div>
    @empty
        <p class="muted">کاری باقی نمانده 🎉</p>
    @endforelse
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:10px">{{ trans('crm::messages.log_activity') }}</h3>
    <form method="post" action="/admin/crm/activities" class="form-grid">
        @csrf
        <label>{{ trans('crm::messages.activity_type') }}
            <select name="activity_type">
                @foreach(\Modules\Crm\Domain\Enums\ActivityType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->labelFa() }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ trans('crm::messages.activity_subject') }} *
            <input type="text" name="subject" required maxlength="255">
        </label>
        <label>{{ trans('crm::messages.due_at') }}
            <input type="datetime-local" name="due_at">
        </label>
        <label>شناسهٔ مخاطب (اختیاری)
            <input type="text" name="contact_id" dir="ltr" maxlength="26">
        </label>
        <label class="full">{{ trans('crm::messages.activity_body') }}
            <textarea name="body" rows="2"></textarea>
        </label>
        <p style="grid-column:1/-1"><button class="btn" type="submit">ثبت</button></p>
    </form>
</div>

<div class="card">
    <h3 style="font-size:.95rem;margin-bottom:8px">🕘 آخرین فعالیت‌ها</h3>
    <div class="timeline">
        @forelse($recent as $activity)
            <div class="ev">
                <strong>{{ $activity->type()->icon() }} {{ $activity->subject() }}</strong>
                @if($activity->isCompleted())<span class="badge green">تکمیل‌شده</span>@endif
                <span class="muted">{{ jalali($activity->createdAt(), 'Y/m/d H:i') }}</span>
            </div>
        @empty
            <p class="muted">—</p>
        @endforelse
    </div>
</div>
@endsection
