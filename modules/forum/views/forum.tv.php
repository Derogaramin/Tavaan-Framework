@extends('forum::layouts.forum')

@section('content')
<div class="forum-layout">
    <div>
        <h1>
            <a href="/forums" class="muted" style="font-size:1rem">{{ trans('forum::messages.forums') }}</a>
            ← {{ $forum->title() }}
        </h1>
        @if($forum->description())
            <p class="muted">{{ $forum->description() }}</p>
        @endif

        @if($children !== [])
            <div class="forum-card">
                <h3>{{ trans('forum::messages.sub_forums') }}</h3>
                @foreach($children as $child)
                    <div class="forum-row">
                        <span class="icon">🗂️</span>
                        <span class="grow">
                            <a href="/forums/{{ $child->slug() }}"><strong>{{ $child->title() }}</strong></a>
                            @if($child->description())<br><span class="muted" style="font-size:.84rem">{{ $child->description() }}</span>@endif
                        </span>
                        <span class="counts">{{ trans_number($child->threadCount()) }} / {{ trans_number($child->postCount()) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="forum-card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                <h2 style="margin:0">{{ trans('forum::messages.threads') }} <span class="muted">({{ trans_number($total) }})</span></h2>
                @auth
                    @if(!$forum->isLocked())
                        <a class="forum-btn" href="/forums/{{ $forum->slug() }}/new">+ {{ trans('forum::messages.new_thread') }}</a>
                    @endif
                @endauth
            </div>

            @if($forum->isLocked())
                <p class="muted">🔒 {{ trans('forum::messages.forum_locked_notice') }}</p>
            @endif

            @forelse($threads as $thread)
                @include('forum::partials.thread-row', ['thread' => $thread])
            @empty
                <p class="muted">{{ trans('forum::messages.no_threads') }}</p>
            @endforelse

            @include('forum::partials.pagination', [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'baseUrl' => '/forums/' . $forum->slug(),
            ])
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
