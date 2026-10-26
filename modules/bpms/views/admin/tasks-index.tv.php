@extends('bpms::layouts.admin')

@section('title', trans('bpms::messages.tasks'))

@section('content')
<div class="topline">
    <h1>☑️ {{ trans('bpms::messages.tasks') }}</h1>
    <div class="spacer"></div>
    <form class="inline" method="get" action="/admin/bpms/tasks" style="display:flex;gap:8px">
        <select name="status" style="width:auto">
            <option value="">—</option>
            @foreach(['pending', 'claimed', 'completed', 'canceled'] as $st)
                <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ trans('bpms::messages.task_' . $st) }}</option>
            @endforeach
        </select>
        <label class="muted" style="display:flex;align-items:center;gap:4px">
            <input type="checkbox" name="overdue" value="1" style="width:auto"> {{ trans('bpms::messages.overdue') }}
        </label>
        <button class="btn ghost sm" type="submit">فیلتر</button>
    </form>
</div>

<div class="card">
    <table class="data">
        <thead>
        <tr><th>عنوان</th><th>نمونه</th><th>{{ trans('bpms::messages.assignee') }}</th><th>{{ trans('bpms::messages.status') }}</th><th>{{ trans('bpms::messages.due_at') }}</th><th>{{ trans('bpms::messages.actions') }}</th></tr>
        </thead>
        <tbody>
        @foreach($tasks as $task)
            <tr>
                <td><a href="/bpms/tasks/{{ $task->id() }}">{{ $task->title() }}</a>
                    @if($task->isEscalated())<span class="badge red">{{ trans('bpms::messages.escalated') }}</span>@endif
                </td>
                <td><a class="muted" href="/admin/bpms/instances/{{ $task->instanceId() }}"><code>{{ substr($task->instanceId(), 0, 8) }}…</code></a></td>
                <td>{{ $task->assigneeType()->value }}{{ $task->assigneeId() !== null ? ':' . $task->assigneeId() : '' }}</td>
                <td>
                    @php $tst = $task->status()->value; @endphp
                    <span class="badge {{ $tst === 'completed' ? 'green' : ($tst === 'canceled' ? 'gray' : ($tst === 'claimed' ? 'blue' : 'amber')) }}">{{ trans('bpms::messages.task_' . $tst) }}</span>
                </td>
                <td class="muted">{{ $task->dueAt() !== null ? jalali($task->dueAt(), 'Y/m/d H:i') : '—' }}</td>
                <td>
                    @if($task->status()->isOpen())
                    <form class="inline" method="post" action="/admin/bpms/tasks/{{ $task->id() }}/assign" style="display:flex;gap:4px;align-items:center">
                        @csrf
                        <input type="hidden" name="assignee_type" value="user">
                        <select name="assignee_id" style="width:auto;padding:3px 6px;font-size:.78rem">
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                            @endforeach
                        </select>
                        <button class="btn sm ghost" type="submit">انتصاب</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        @if(count($tasks) === 0)
            <tr><td colspan="6" class="muted">{{ trans('bpms::messages.no_results') }}</td></tr>
        @endif
        </tbody>
    </table>

    @include('bpms::partials.pagination', ['baseUrl' => '/admin/bpms/tasks', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>
@endsection
