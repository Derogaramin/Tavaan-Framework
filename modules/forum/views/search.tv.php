@extends('forum::layouts.forum')

@section('content')
<div class="forum-layout">
    <div>
        <h1>{{ trans('forum::messages.search_results') }}</h1>

        <div class="forum-card">
            <form method="get" action="/forums/search" class="forum-form">
                <div class="field">
                    <label for="q">{{ trans('forum::messages.search') }}</label>
                    <input type="text" id="q" name="q" value="{{ $query }}" placeholder="{{ trans('forum::messages.search_placeholder') }}">
                </div>
                <button class="forum-btn" type="submit">{{ trans('forum::messages.search_submit') }}</button>
            </form>
        </div>

        @if($query !== '')
            <div class="forum-card">
                <h2>{{ trans('forum::messages.threads') }} <span class="muted">({{ trans_number(count($result['threads'])) }})</span></h2>
                @forelse($result['threads'] as $thread)
                    @include('forum::partials.thread-row', ['thread' => $thread])
                @empty
                    <p class="muted">{{ trans('forum::messages.no_results') }}</p>
                @endforelse
            </div>

            <div class="forum-card">
                <h2>{{ trans('forum::messages.posts') }} <span class="muted">({{ trans_number(count($result['posts'])) }})</span></h2>
                @forelse($result['posts'] as $post)
                    <div class="forum-row">
                        <span class="icon">💬</span>
                        <span class="grow">
                            <a href="/threads/{{ $post->threadId() }}#post-{{ $post->id() }}">
                                {{ mb_substr(strip_tags($post->body()), 0, 140) }}…
                            </a>
                            @if($post->isSolution())<span class="badge solved">✅ {{ trans('forum::messages.solution') }}</span>@endif
                        </span>
                    </div>
                @empty
                    <p class="muted">{{ trans('forum::messages.no_results') }}</p>
                @endforelse
            </div>
        @endif
    </div>

    <aside>
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
