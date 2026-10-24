@php

    $postAuthorName = $names[(string) $post->authorId()] ?? 'کاربر';
    $postAuthorUsername = $usernames[(string) $post->authorId()] ?? ('user-' . $post->authorId());
    $myVote = $myVotes[$post->id()] ?? null;
    $viewerId = isset($viewerId) && $viewerId !== null ? (int) $viewerId : null;
    $isFirstPost = $first !== null && $post->id() === $first->id();
@endphp
<article class="post {{ $post->isSolution() ? 'solution' : '' }}" id="post-{{ $post->id() }}">
    <div class="post-head">
        <span class="author"><a href="/members/{{ $postAuthorUsername }}">{{ $postAuthorName }}</a></span>
        <span class="muted" style="font-size:.8rem">{{ '@' . $postAuthorUsername }}</span>
        @if($isFirstPost)<span class="badge new">{{ trans('forum::messages.thread_starter') }}</span>@endif
        @if($post->isSolution())<span class="badge solved">✅ {{ trans('forum::messages.solution') }}</span>@endif
        <span class="date" style="margin-inline-start:auto">
            {{ jalali($post->createdAt(), 'Y/m/d H:i') }}
            @if($post->updatedAt() !== null)<span class="muted">({{ trans('forum::messages.edited') }})</span>@endif
        </span>
    </div>

    @if($post->isVisible())
        <div class="post-body">{!! $post->body() !!}</div>
    @elseif($post->status()->value === 'hidden')
        <div class="post-body muted">🚫 {{ trans('forum::messages.post_hidden_notice') }}</div>
    @endif

    <div class="post-actions">
        <span class="vote-box">
            @auth
                @if($viewerId !== null && ($canVote ?? false) && $post->authorId() !== $viewerId)
                    <form method="post" action="/posts/{{ $post->id() }}/vote" class="inline">
                        @csrf
                        <input type="hidden" name="value" value="1">
                        <button type="submit" class="vote-btn {{ $myVote === 1 ? 'active-up' : '' }}" title="{{ trans('forum::messages.upvote') }}">▲</button>
                    </form>
                @endif
            @endauth
            <span class="vote-score" id="score-{{ $post->id() }}">{{ trans_number($post->voteScore()) }}</span>
            @auth
                @if($viewerId !== null && ($canVote ?? false) && $post->authorId() !== $viewerId)
                    <form method="post" action="/posts/{{ $post->id() }}/vote" class="inline">
                        @csrf
                        <input type="hidden" name="value" value="-1">
                        <button type="submit" class="vote-btn {{ $myVote === -1 ? 'active-down' : '' }}" title="{{ trans('forum::messages.downvote') }}">▼</button>
                    </form>
                @endif
            @endauth
        </span>

        @auth
            @if(!$isFirstPost && ($canReply ?? false))
                <a class="btn-link" href="/threads/{{ $thread->slug() }}?quote={{ $post->id() }}#reply">{{ trans('forum::messages.quote') }}</a>
            @endif
            @if(($canSolution ?? false) && !$isFirstPost && $post->isVisible())
                <form method="post" action="/posts/{{ $post->id() }}/mark-solution" class="inline">
                    @csrf
                    <button type="submit" class="btn-link">
                        {{ $post->isSolution() ? trans('forum::messages.unmark_solution') : trans('forum::messages.mark_solution') }}
                    </button>
                </form>
            @endif
            @if($viewerId !== null && ($canFlag ?? false) && $post->authorId() !== $viewerId && $post->isVisible())
                <form method="post" action="/posts/{{ $post->id() }}/flag" class="inline"
                      onsubmit="var r=prompt('{{ trans('forum::messages.flag_reason_prompt') }}');if(r===null)return false;this.reason.value=r;return true;">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="btn-link">🚩 {{ trans('forum::messages.flag') }}</button>
                </form>
            @endif
            @if($viewerId !== null && ($canEditPost ?? false) && $post->authorId() === $viewerId && $post->isVisible())
                <a class="btn-link" href="/posts/{{ $post->id() }}/edit">{{ trans('forum::messages.edit') }}</a>
            @endif
            @if($canModerate ?? false)
                @if($post->isVisible())
                    <form method="post" action="/admin/forum/posts/{{ $post->id() }}/hide" class="inline">
                        @csrf
                        <button type="submit" class="btn-link" style="color:#b45309">{{ trans('forum::messages.hide') }}</button>
                    </form>
                @elseif($post->status()->value === 'hidden')
                    <form method="post" action="/admin/forum/posts/{{ $post->id() }}/unhide" class="inline">
                        @csrf
                        <button type="submit" class="btn-link" style="color:#059669">{{ trans('forum::messages.unhide') }}</button>
                    </form>
                @endif
                <form method="post" action="/admin/forum/posts/{{ $post->id() }}" class="inline"
                      onsubmit="return confirm('{{ trans('forum::messages.delete_confirm') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-link" style="color:#dc2626">{{ trans('forum::messages.delete') }}</button>
                </form>
            @endif
        @endauth
    </div>
</article>
