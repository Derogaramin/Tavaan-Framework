@extends('forum::layouts.forum')

@section('content')
<div class="forum-layout">
    <div>
        <h1>{{ trans('forum::messages.forums') }}</h1>

        @forelse($roots as $branch)
            @php $rootForum = $branch['forum']; @endphp
            <div class="forum-card">
                <h2>
                    <a href="/forums/{{ $rootForum->slug() }}">{{ $rootForum->title() }}</a>
                    @if($rootForum->isLocked())<span class="badge locked">🔒 {{ trans('forum::messages.locked') }}</span>@endif
                </h2>
                @if($rootForum->description())
                    <p class="muted">{{ $rootForum->description() }}</p>
                @endif

                @foreach($branch['children'] as $child)
                    <div class="forum-row">
                        <span class="icon">🗂️</span>
                        <span class="grow">
                            <a href="/forums/{{ $child->slug() }}"><strong>{{ $child->title() }}</strong></a>
                            @if($child->isLocked())<span class="badge locked">🔒</span>@endif
                            @if($child->description())
                                <br><span class="muted" style="font-size:.84rem">{{ $child->description() }}</span>
                            @endif
                        </span>
                        <span class="counts">
                            {{ trans('forum::messages.threads') }}: {{ trans_number($child->threadCount()) }}<br>
                            {{ trans('forum::messages.posts') }}: {{ trans_number($child->postCount()) }}
                        </span>
                    </div>
                @endforeach

                @if($branch['children'] === [])
                    <div class="forum-row">
                        <span class="counts">
                            {{ trans('forum::messages.threads') }}: {{ trans_number($rootForum->threadCount()) }} •
                            {{ trans('forum::messages.posts') }}: {{ trans_number($rootForum->postCount()) }}
                        </span>
                    </div>
                @endif
            </div>
        @empty
            <div class="forum-card">
                <p class="muted">{{ trans('forum::messages.no_forums') }}</p>
            </div>
        @endforelse

        <div class="forum-card">
            <h3>{{ trans('forum::messages.latest_threads') }}</h3>
            @forelse($latest as $thread)
                @include('forum::partials.thread-row', ['thread' => $thread])
            @empty
                <p class="muted">{{ trans('forum::messages.no_threads') }}</p>
            @endforelse
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
            <strong>{{ trans('forum::messages.stats') }}</strong>
            <p class="muted">
                {{ trans('forum::messages.stat_forums', ['n' => trans_number($stats['forums'])]) }}<br>
                {{ trans('forum::messages.stat_threads', ['n' => trans_number($stats['threads'])]) }}<br>
                {{ trans('forum::messages.stat_posts', ['n' => trans_number($stats['posts'])]) }}<br>
                {{ trans('forum::messages.stat_members', ['n' => trans_number($stats['members'])]) }}
            </p>
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
