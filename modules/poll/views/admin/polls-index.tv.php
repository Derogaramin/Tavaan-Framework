@extends('poll::admin.layout')

@section('content')
@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

<div class="card">
    <h2>{{ trans('poll::messages.admin_polls') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/polls" style="margin-block-end:1rem">
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ trans('poll::messages.all_statuses') }}</option>
            @foreach(['draft', 'open', 'closed'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ trans('poll::status.' . $s) }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('poll::messages.question_col') }}</th>
                <th>{{ trans('poll::messages.status_col') }}</th>
                <th>{{ trans('poll::messages.votes_col') }}</th>
                <th>{{ trans('poll::messages.date_col') }}</th>
                <th>{{ trans('poll::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($polls as $item)
            <tr>
                <td>
                    <a href="/admin/polls/{{ $item->id() }}/edit">{{ $item->question() }}</a>
                    @if($item->status()->isPublic())
                        <a class="muted" href="/polls/{{ $item->slug() }}" target="_blank" title="{{ trans('poll::messages.show_poll') }}">↗</a>
                    @endif
                </td>
                <td><span class="badge {{ $item->status()->value }}">{{ trans('poll::status.' . $item->status()->value) }}</span></td>
                <td>{{ trans_number((int) ($voteCounts[$item->id()] ?? 0)) }}</td>
                <td>{{ $item->createdAt() !== null ? jalali($item->createdAt(), 'Y/m/d') : '—' }}</td>
                <td class="row-actions">
                    <a class="btn gray" href="/admin/polls/{{ $item->id() }}/edit">{{ trans('poll::messages.edit') }}</a>
                    @if($item->status()->value !== 'open')
                    <form class="inline" method="post" action="/admin/polls/{{ $item->id() }}/open">
                        @csrf
                        <button class="btn green" type="submit">{{ trans('poll::messages.open_action') }}</button>
                    </form>
                    @else
                    <form class="inline" method="post" action="/admin/polls/{{ $item->id() }}/close">
                        @csrf
                        <button class="btn" type="submit">{{ trans('poll::messages.close_action') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/polls/{{ $item->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('poll::messages.confirm_delete') }}')">{{ trans('poll::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ trans('poll::messages.no_polls') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $currentPage = (int) ($page ?? 1);
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $currentPage)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/polls?page={{ $p }}&status={{ $status }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
