@php
@endphp
<div class="forum-row">
    <span class="icon">{{ $thread->isSolved() ? '✅' : '🗨️' }}</span>
    <span class="grow">
        <a href="/threads/{{ $thread->slug() }}"><strong>{{ $thread->title() }}</strong></a>
        @if($thread->isPinned())<span class="badge pinned">📌 {{ trans('forum::messages.pinned') }}</span>@endif
        @if($thread->isLocked())<span class="badge locked">🔒 {{ trans('forum::messages.locked') }}</span>@endif
        @if($thread->isSolved())<span class="badge solved">{{ trans('forum::messages.solved') }}</span>@endif
    </span>
    <span class="counts">
        {{ trans('forum::messages.replies') }}: {{ trans_number($thread->replyCount()) }}<br>
        {{ trans('forum::messages.views') }}: {{ trans_number($thread->viewCount()) }}
    </span>
</div>
