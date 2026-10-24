@extends('news::admin.layout')

@section('content')
@php $ok = \Tavaan\Core\Facade\Facades\Session::pull('news_comment_ok'); @endphp
@if(is_string($ok) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

<div class="card">
    <h2>{{ trans('news::messages.admin_comments') }}
        @if($pendingCount > 0)
            <span class="badge pending">{{ trans('news::messages.pending_count', ['count' => trans_number($pendingCount)]) }}</span>
        @endif
    </h2>

    <form method="get" action="/admin/news/comments" style="margin-block-end:1rem">
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ trans('news::messages.all_statuses') }}</option>
            @foreach(['pending', 'approved', 'spam'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ trans('comment::messages.status.' . $s) }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('news::messages.comment_author_col') }}</th>
                <th>{{ trans('news::messages.comment_body_col') }}</th>
                <th>{{ trans('news::messages.comment_article_col') }}</th>
                <th>{{ trans('news::messages.status_col') }}</th>
                <th>{{ trans('news::messages.date_col') }}</th>
                <th>{{ trans('news::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($comments as $comment)
            <tr>
                <td>
                    <strong>{{ $comment->authorName }}</strong><br>
                    <span class="muted" dir="ltr">{{ $comment->authorEmail }}</span>
                </td>
                <td style="max-width:340px">{{ \Tavaan\Support\Str::limit($comment->body, 90) }}</td>
                <td>
                    @php $title = $articleTitles[$comment->commentableId] ?? null; @endphp
                    @if($title !== null)
                        {{ \Tavaan\Support\Str::limit($title, 40) }}
                    @else
                        <span class="muted">{{ $comment->commentableId }}</span>
                    @endif
                </td>
                <td><span class="badge {{ $comment->status->value }}">{{ trans('comment::messages.status.' . $comment->status->value) }}</span></td>
                <td>{{ $comment->createdAt !== null ? jalali($comment->createdAt, 'Y/m/d H:i') : '—' }}</td>
                <td class="row-actions">
                    @if($comment->status->value !== 'approved')
                    <form class="inline" method="post" action="/admin/news/comments/{{ $comment->id }}/approve">
                        @csrf
                        <button class="btn green" type="submit">{{ trans('news::messages.approve') }}</button>
                    </form>
                    @endif
                    @if($comment->status->value !== 'spam')
                    <form class="inline" method="post" action="/admin/news/comments/{{ $comment->id }}/spam">
                        @csrf
                        <button class="btn gray" type="submit">{{ trans('news::messages.mark_spam') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/news/comments/{{ $comment->id }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('news::messages.confirm_delete') }}')">{{ trans('news::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('news::messages.no_comments') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $lastPage = (int) max(1, ceil($total / max(1, $perPage))); @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/news/comments?page={{ $p }}&status={{ $status }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
