@extends('bpms::layouts.web')

@section('title', trans('bpms::messages.my_tasks'))

@section('content')
<div class="card">
    <h1>☑️ {{ trans('bpms::messages.claimed_tasks') }}</h1>
    @if(count($claimed) > 0)
        <table class="data">
            <thead><tr><th>عنوان</th><th>{{ trans('bpms::messages.due_at') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($claimed as $task)
                <tr>
                    <td><a href="/bpms/tasks/{{ $task->id() }}">{{ $task->title() }}</a></td>
                    <td class="muted">
                        @if($task->dueAt() !== null)
                            {{ jalali($task->dueAt(), 'Y/m/d H:i') }}
                            @if($task->isOverdue(\Tavaan\Support\Time\Clock::now()))<span class="badge red">{{ trans('bpms::messages.overdue') }}</span>@endif
                        @else — @endif
                    </td>
                    <td><a class="btn sm" href="/bpms/tasks/{{ $task->id() }}">ادامه</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
    @endif
</div>

<div class="card">
    <h1>📥 {{ trans('bpms::messages.available_tasks') }}</h1>
    @if(count($available) > 0)
        <table class="data">
            <thead><tr><th>عنوان</th><th>{{ trans('bpms::messages.assignee') }}</th><th>{{ trans('bpms::messages.due_at') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($available as $task)
                <tr>
                    <td><a href="/bpms/tasks/{{ $task->id() }}">{{ $task->title() }}</a>
                        @if($task->isEscalated())<span class="badge red">{{ trans('bpms::messages.escalated') }}</span>@endif
                    </td>
                    <td class="muted">{{ $task->assigneeType()->value }}{{ $task->assigneeId() !== null ? ':' . $task->assigneeId() : '' }}</td>
                    <td class="muted">{{ $task->dueAt() !== null ? jalali($task->dueAt(), 'Y/m/d H:i') : '—' }}</td>
                    <td>
                        <a class="btn sm ghost" href="/bpms/tasks/{{ $task->id() }}">مشاهده</a>
                        @if($task->status()->value === 'pending' && \Tavaan\Core\Facade\Facades\Gate::allows('bpms.task.claim'))
                            <form class="inline" method="post" action="/bpms/tasks/{{ $task->id() }}/claim">
                                @csrf
                                <button class="btn sm" type="submit">{{ trans('bpms::messages.claim') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="muted">{{ trans('bpms::messages.no_results') }}</div>
    @endif
</div>
@endsection
