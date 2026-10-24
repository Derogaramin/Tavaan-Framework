@extends('forum::layouts.forum')

@section('content')
<div class="forum-card" style="max-width:760px;margin-inline:auto">
    <h1>{{ trans('forum::messages.edit_post') }}</h1>

    @if(!empty($errors))
        <div class="forum-flash error">
            @foreach($errors as $fieldErrors)
                @foreach((array) $fieldErrors as $message)
                    <div>{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    <form method="post" action="/posts/{{ $post->id() }}" class="forum-form">
        @csrf
        <div class="field">
            <label for="body">{{ trans('forum::messages.reply_body') }}</label>
            <textarea id="body" name="body" required minlength="2">{{ strip_tags($post->body()) }}</textarea>
        </div>
        <button class="forum-btn" type="submit">{{ trans('forum::messages.save') }}</button>
        <a class="forum-btn gray" href="/threads/{{ $post->threadId() }}">{{ trans('forum::messages.cancel') }}</a>
    </form>
</div>
@endsection
