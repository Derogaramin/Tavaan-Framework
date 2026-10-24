@extends('forum::admin.layout')

@section('content')
<div class="card">
    <h2>{{ trans('forum::messages.admin_threads') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/forum/threads" style="margin-block-end:1rem">
        <select name="forum_id" onchange="this.form.submit()">
            <option value="0">{{ trans('forum::messages.all_forums') }}</option>
            @foreach($forums as $candidate)
                <option value="{{ $candidate->id() }}" {{ $forumId === (int) $candidate->id() ? 'selected' : '' }}>
                    {{ $candidate->title() }}
                </option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
        <tr>
            <th>{{ trans('forum::messages.title') }}</th>
            <th>{{ trans('forum::messages.replies') }}</th>
            <th>{{ trans('forum::messages.views') }}</th>
            <th>{{ trans('forum::messages.status') }}</th>
            <th>{{ trans('forum::messages.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($threads as $thread)
            <tr>
                <td>
                    <a href="/threads/{{ $thread->slug() }}" target="_blank"><strong>{{ $thread->title() }}</strong></a>
                    <br><span class="muted" style="font-size:.78rem">{{ jalali($thread->createdAt(), 'Y/m/d H:i') }}</span>
                </td>
                <td>{{ trans_number($thread->replyCount()) }}</td>
                <td>{{ trans_number($thread->viewCount()) }}</td>
                <td>
                    @if($thread->isPinned())<span class="badge pinned">📌</span>@endif
                    @if($thread->isLocked())<span class="badge locked">🔒</span>@endif
                    @if($thread->isSolved())<span class="badge solved">✅</span>@endif
                </td>
                <td>
                    <div class="row-actions">
                        @if($canLock)
                            <form method="post" action="/admin/forum/threads/{{ $thread->id() }}/lock" class="inline">
                                @csrf
                                <button class="btn amber" type="submit">
                                    {{ $thread->isLocked() ? trans('forum::messages.unlock') : trans('forum::messages.lock') }}
                                </button>
                            </form>
                        @endif
                        @if($canPin)
                            <form method="post" action="/admin/forum/threads/{{ $thread->id() }}/pin" class="inline">
                                @csrf
                                <button class="btn gray" type="submit">
                                    {{ $thread->isPinned() ? trans('forum::messages.unpin') : trans('forum::messages.pin') }}
                                </button>
                            </form>
                        @endif
                        @if($canMove)
                            <form method="post" action="/admin/forum/threads/{{ $thread->id() }}/move" class="inline">
                                @csrf
                                <select name="forum_id" style="width:auto;display:inline-block">
                                    @foreach($forums as $candidate)
                                        <option value="{{ $candidate->id() }}" {{ (int) $candidate->id() === $thread->forumId() ? 'selected' : '' }}>
                                            {{ $candidate->title() }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn" type="submit">{{ trans('forum::messages.move') }}</button>
                            </form>
                        @endif
                        @if($canDelete)
                            <form method="post" action="/admin/forum/threads/{{ $thread->id() }}" class="inline"
                                  onsubmit="return confirm('{{ trans('forum::messages.delete_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="btn red" type="submit">{{ trans('forum::messages.delete') }}</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ trans('forum::messages.no_threads') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $totalPages = (int) max(1, ceil($total / max(1, $perPage))); @endphp
    @if($totalPages > 1)
        <div class="pagination">
            @for($p = 1; $p <= $totalPages; $p++)
                @if($p === $page)
                    <span class="current">{{ trans_number($p) }}</span>
                @else
                    <a href="/admin/forum/threads?page={{ $p }}&forum_id={{ $forumId }}">{{ trans_number($p) }}</a>
                @endif
            @endfor
        </div>
    @endif
</div>
@endsection
