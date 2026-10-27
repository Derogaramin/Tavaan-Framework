@extends('comment::admin.layout')

@section('content')
@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $fieldErrors)
            @foreach((array) $fieldErrors as $err)
                <div>{{ $err }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="stat-grid">
    <div class="stat"><div class="num">{{ trans_number($stats['pending']) }}</div><div class="muted">{{ trans('comment::messages.ui.stat_pending') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($stats['approved']) }}</div><div class="muted">{{ trans('comment::messages.ui.stat_approved') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($stats['spam']) }}</div><div class="muted">{{ trans('comment::messages.ui.stat_spam') }}</div></div>
    <div class="stat"><div class="num">{{ trans_number($stats['total']) }}</div><div class="muted">{{ trans('comment::messages.ui.stat_total') }}</div></div>
</div>

<div class="card">
    <h2>{{ trans('comment::messages.ui.queue') }}</h2>

    <form method="get" action="/admin/comments" class="toolbar">
        <select name="status">
            <option value="">{{ trans('comment::messages.ui.all_statuses') }}</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>{{ trans('comment::messages.status.pending') }}</option>
            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>{{ trans('comment::messages.status.approved') }}</option>
            <option value="spam" {{ $status === 'spam' ? 'selected' : '' }}>{{ trans('comment::messages.status.spam') }}</option>
        </select>
        <select name="type">
            <option value="">{{ trans('comment::messages.ui.all_types') }}</option>
            @foreach($typeStats as $ts)
                <option value="{{ $ts['type'] }}" {{ $type === $ts['type'] ? 'selected' : '' }}>
                    {{ $registry->has($ts['type']) ? $registry->label($ts['type']) : $ts['type'] }} ({{ trans_number($ts['pending']) }}/{{ trans_number($ts['total']) }})
                </option>
            @endforeach
        </select>
        <button class="btn gray" type="submit">🔍</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('comment::messages.ui.col_author') }}</th>
                <th>{{ trans('comment::messages.ui.col_body') }}</th>
                <th>{{ trans('comment::messages.ui.col_subject') }}</th>
                <th>{{ trans('comment::messages.ui.col_status') }}</th>
                <th>{{ trans('comment::messages.ui.col_date') }}</th>
                <th>{{ trans('comment::messages.ui.col_actions') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($comments as $c)
            <tr>
                <td>
                    <strong>{{ $c->authorName }}</strong><br>
                    <span class="muted" dir="ltr" style="font-size:.75rem">{{ $c->authorEmail }}</span>
                </td>
                <td class="comment-body">{{ mb_strimwidth($c->body, 0, 240, '…') }}</td>
                <td>
                    <span class="badge pending">{{ $registry->has($c->commentableType) ? $registry->label($c->commentableType) : $c->commentableType }}</span><br>
                    <span class="muted" style="font-size:.75rem" dir="ltr">{{ $c->commentableId }}</span>
                    @php $subjectUrl = $registry->has($c->commentableType) ? $registry->url($c->commentableType, $c->commentableId) : ''; @endphp
                    @if($subjectUrl !== '')
                        <a href="{{ $subjectUrl }}" target="_blank" title="نمایش موضوع">↗</a>
                    @endif
                </td>
                <td><span class="badge {{ $c->status->value }}">{{ trans('comment::messages.status.' . $c->status->value) }}</span></td>
                <td>{{ $c->createdAt !== null ? jalali($c->createdAt, 'Y/m/d H:i') : '—' }}</td>
                <td class="row-actions">
                    @if($c->status->value !== 'approved')
                    <form class="inline" method="post" action="/admin/comments/{{ $c->id }}/approve">
                        @csrf
                        <button class="btn green" type="submit">{{ trans('comment::messages.ui.approve') }}</button>
                    </form>
                    @else
                    <form class="inline" method="post" action="/admin/comments/{{ $c->id }}/unapprove">
                        @csrf
                        <button class="btn amber" type="submit">{{ trans('comment::messages.ui.unapprove') }}</button>
                    </form>
                    @endif
                    @if($c->status->value !== 'spam')
                    <form class="inline" method="post" action="/admin/comments/{{ $c->id }}/spam">
                        @csrf
                        <button class="btn gray" type="submit">{{ trans('comment::messages.ui.spam') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/comments/{{ $c->id }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('comment::messages.ui.confirm_delete') }}')">{{ trans('comment::messages.ui.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('comment::messages.ui.no_comments_admin') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $pageUrl = static function (int $p) use ($queryString): string {
            $params = [];
            parse_str($queryString, $params);
            $params['page'] = $p;
            return '/admin/comments?' . http_build_query($params);
        };
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @if($page > 1)<a href="{{ $pageUrl($page - 1) }}">‹</a>@endif
        @for($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="{{ $pageUrl($p) }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
        @if($page < $lastPage)<a href="{{ $pageUrl($page + 1) }}">›</a>@endif
    </nav>
    @endif
</div>
@endsection
