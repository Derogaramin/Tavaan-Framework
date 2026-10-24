@extends('forum::layouts.forum')

@section('pageTitle', $thread->title() . ' — ' . trans('forum::messages.forum_title'))

@section('content')
@php
    $authId = \Tavaan\Core\Facade\Facades\Auth::id();
    $viewerId = $authId === null ? null : (int) $authId;
    $liveOnText = json_encode(trans('forum::messages.live_on'), JSON_UNESCAPED_UNICODE);
    $liveNewPostText = json_encode(trans('forum::messages.live_new_post'), JSON_UNESCAPED_UNICODE);
    $canVote = $viewerId !== null && \Tavaan\Core\Facade\Facades\Gate::allows('forum.post.vote');
    $canFlag = $viewerId !== null && \Tavaan\Core\Facade\Facades\Gate::allows('forum.flag.create');
    $canEditPost = $viewerId !== null && \Tavaan\Core\Facade\Facades\Gate::allows('forum.post.create');
    $solutionAllowed = ($canSolution ?? false) || ($isThreadAuthor ?? false);
    $honeypotField = (string) config('forum.spam.honeypot_field', 'website');
    $postPartialData = [
        'thread' => $thread,
        'names' => $names,
        'usernames' => $usernames,
        'myVotes' => $myVotes,
        'viewerId' => $viewerId,
        'canVote' => $canVote,
        'canFlag' => $canFlag,
        'canEditPost' => $canEditPost,
        'canReply' => $canReply,
        'canSolution' => $solutionAllowed,
        'canModerate' => $canModerate,
    ];
@endphp

<div class="forum-layout">
    <div>
        <p class="muted">
            <a href="/forums">{{ trans('forum::messages.forums') }}</a>
            @if($forum !== null) ← <a href="/forums/{{ $forum->slug() }}">{{ $forum->title() }}</a>@endif
        </p>

        <h1 id="posts">
            {{ $thread->title() }}
            @if($thread->isPinned())<span class="badge pinned">📌 {{ trans('forum::messages.pinned') }}</span>@endif
            @if($thread->isLocked())<span class="badge locked">🔒 {{ trans('forum::messages.locked') }}</span>@endif
            @if($thread->isSolved())<span class="badge solved">✅ {{ trans('forum::messages.solved') }}</span>@endif
        </h1>
        <div class="forum-meta" style="margin-block-end:1rem">
            <span>👁 {{ trans_number($thread->viewCount()) }} {{ trans('forum::messages.views') }}</span>
            <span>💬 {{ trans_number($thread->replyCount()) }} {{ trans('forum::messages.replies') }}</span>
            <span id="live-indicator" class="live-off"><span class="live-dot"></span><span id="live-text">{{ trans('forum::messages.live_off') }}</span></span>
            @auth
                <form method="post" action="/threads/{{ $thread->id() }}/{{ $subscribed ? 'unsubscribe' : 'subscribe' }}" class="inline">
                    @csrf
                    <button type="submit" class="btn-link">
                        {{ $subscribed ? trans('forum::messages.unsubscribe') : trans('forum::messages.subscribe') }}
                    </button>
                </form>
            @endauth
        </div>

        @if($first !== null)
            @include('forum::partials.post', ['post' => $first, 'first' => $first] + $postPartialData)
        @endif

        @forelse($posts as $post)
            @if($first === null || $post->id() !== $first->id())
                @include('forum::partials.post', ['post' => $post, 'first' => $first] + $postPartialData)
            @endif
        @empty
            @if($first === null)
                <div class="forum-card"><p class="muted">{{ trans('forum::messages.no_posts') }}</p></div>
            @endif
        @endforelse

        @include('forum::partials.pagination', [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'baseUrl' => '/threads/' . $thread->slug(),
        ])

        <div class="forum-card" id="reply">
            <h3>{{ trans('forum::messages.reply') }}</h3>
            @auth
                @if($canReply)
                    <form method="post" action="/threads/{{ $thread->id() }}/posts" class="forum-form">
                        @csrf
                        <div class="field">
                            <label for="body">{{ trans('forum::messages.reply_body') }}</label>
                            <textarea id="body" name="body" required minlength="2">{{ $quoteText }}</textarea>
                        </div>
                        {{-- honeypot (DOC-094 §7) — برای انسان نامرئی --}}
                        <div class="hp-field" aria-hidden="true">
                            <label for="{{ $honeypotField }}">{{ $honeypotField }}</label>
                            <input type="text" id="{{ $honeypotField }}" name="{{ $honeypotField }}" value="" tabindex="-1" autocomplete="off">
                        </div>
                        <button class="forum-btn" type="submit">{{ trans('forum::messages.reply_submit') }}</button>
                        <span class="muted" style="font-size:.8rem">{{ trans('forum::messages.markdown_hint') }}</span>
                    </form>
                @else
                    <p class="muted">
                        {{ $thread->isLocked() ? trans('forum::messages.thread_locked_notice') : trans('forum::messages.cannot_reply') }}
                    </p>
                @endif
            @else
                <p class="muted">{{ trans('forum::messages.login_to_reply') }} <a href="/login">{{ trans('forum::messages.nav_login') }}</a></p>
            @endauth
        </div>
    </div>

    <aside>
        <div class="forum-card">
            <form method="get" action="/forums/search" class="forum-form">
                <div class="field">
                    <label for="q">{{ trans('forum::messages.search') }}</label>
                    <input type="text" id="q" name="q" placeholder="{{ trans('forum::messages.search_placeholder') }}">
                </div>
                <button class="forum-btn" type="submit">{{ trans('forum::messages.search_submit') }}</button>
            </form>
        </div>

        <div class="forum-card">
            <strong>{{ trans('forum::messages.top_members') }}</strong>
            <ul class="member-list">
                @forelse($topMembers as $member)
                    <li>
                        <a href="/members/{{ $member->username() }}">{{ $member->username() }}</a>
                        <span class="badge karma">⭐ {{ trans_number($member->karma()) }}</span>
                    </li>
                @empty
                    <li class="muted">{{ trans('forum::messages.no_members') }}</li>
                @endforelse
            </ul>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
    // 🔒 Realtime بی‌درنگ با SSE موتور realtime — بدون ext (AC-FORUM-08).
    // کانال موضوع: forum.thread.{ULID} → رویداد post.created
    (function () {
        var channel = 'forum.thread.{{ $thread->id() }}';
        var indicator = document.getElementById('live-indicator');
        var text = document.getElementById('live-text');
        if (!window.EventSource || !indicator) { return; }
        try {
            var source = new EventSource('/events/stream?channels=' + encodeURIComponent(channel));
            source.onopen = function () {
                indicator.classList.remove('live-off');
                text.textContent = {!! $liveOnText !!};
            };
            source.addEventListener('post.created', function (e) {
                var banner = document.getElementById('live-banner');
                if (!banner) {
                    banner = document.createElement('div');
                    banner.id = 'live-banner';
                    banner.className = 'forum-flash ok';
                    banner.innerHTML = '';
                    var link = document.createElement('a');
                    link.href = window.location.href.split('#')[0];
                    link.textContent = {!! $liveNewPostText !!};
                    banner.appendChild(link);
                    indicator.parentNode.parentNode.insertBefore(banner, indicator.parentNode.nextSibling);
                }
            });
            source.onerror = function () {
                indicator.classList.add('live-off');
            };
        } catch (err) { /* best-effort */ }
    })();
</script>
@endpush
