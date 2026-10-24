@extends('forum::layouts.forum')

@section('content')
<div class="forum-card" style="max-width:760px;margin-inline:auto">
    <h1>{{ trans('forum::messages.new_thread') }}</h1>
    <p class="muted">
        {{ trans('forum::messages.forum_label') }}:
        <a href="/forums/{{ $forum->slug() }}">{{ $forum->title() }}</a>
    </p>

    @if(!empty($errors))
        <div class="forum-flash error">
            @foreach($errors as $fieldErrors)
                @foreach((array) $fieldErrors as $message)
                    <div>{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    <form method="post" action="/threads" class="forum-form">
        @csrf
        <input type="hidden" name="forum_id" value="{{ $forum->id() }}">
        <div class="field">
            <label for="title">{{ trans('forum::messages.thread_title') }}</label>
            <input type="text" id="title" name="title" required minlength="5" maxlength="255"
                   value="{{ $old['title'] ?? '' }}">
        </div>
        <div class="field">
            <label for="body">{{ trans('forum::messages.thread_body') }}</label>
            <textarea id="body" name="body" required minlength="2">{{ $old['body'] ?? '' }}</textarea>
        </div>
        <button class="forum-btn" type="submit">{{ trans('forum::messages.thread_submit') }}</button>
        <a class="forum-btn gray" href="/forums/{{ $forum->slug() }}">{{ trans('forum::messages.cancel') }}</a>
        <p class="muted" style="font-size:.8rem">{{ trans('forum::messages.markdown_hint') }}</p>
    </form>
</div>
@endsection
