@extends('forum::layouts.forum')

@section('content')
<div class="forum-card" style="max-width:760px;margin-inline:auto">
    <h1>
        👤 {{ $user?->name ?? $profile->username() }}
        <span class="muted" style="font-size:1rem">{{ '@' . $profile->username() }}</span>
    </h1>

    <p>
        <span class="badge karma">⭐ {{ trans('forum::messages.karma') }}: {{ trans_number($profile->karma()) }}</span>
    </p>

    @if($profile->bio())
        <p>{{ $profile->bio() }}</p>
    @endif

    <table>
        <tr>
            <th>{{ trans('forum::messages.stat_threads_label') }}</th>
            <td>{{ trans_number($profile->threadCount()) }}</td>
        </tr>
        <tr>
            <th>{{ trans('forum::messages.stat_posts_label') }}</th>
            <td>{{ trans_number($profile->postCount()) }}</td>
        </tr>
        <tr>
            <th>{{ trans('forum::messages.stat_solutions_label') }}</th>
            <td>{{ trans_number($profile->solutionCount()) }}</td>
        </tr>
        @if($profile->createdAt() !== null)
        <tr>
            <th>{{ trans('forum::messages.member_since') }}</th>
            <td>{{ jalali($profile->createdAt(), 'Y/m/d') }}</td>
        </tr>
        @endif
    </table>

    @if($profile->signature())
        <p class="muted" style="border-block-start:1px dashed var(--f-border);padding-block-start:.6rem">
            — {{ $profile->signature() }}
        </p>
    @endif

    @if($isSelf)
        <p><a class="forum-btn gray" href="/members/{{ $profile->username() }}/edit">{{ trans('forum::messages.edit_profile') }}</a></p>
    @endif

    @if($recentThreads !== [])
        <h3>{{ trans('forum::messages.recent_threads') }}</h3>
        @foreach($recentThreads as $thread)
            @include('forum::partials.thread-row', ['thread' => $thread])
        @endforeach
    @endif
</div>
@endsection
