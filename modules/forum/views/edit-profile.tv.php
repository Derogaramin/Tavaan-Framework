@extends('forum::layouts.forum')

@section('content')
<div class="forum-card" style="max-width:640px;margin-inline:auto">
    <h1>{{ trans('forum::messages.edit_profile') }}</h1>

    @if(!empty($errors))
        <div class="forum-flash error">
            @foreach($errors as $fieldErrors)
                @foreach((array) $fieldErrors as $message)
                    <div>{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    <form method="post" action="/members/{{ $profile->username() }}" class="forum-form">
        @csrf
        <div class="field">
            <label for="username">{{ trans('forum::messages.username') }}</label>
            <input type="text" id="username" name="username" required minlength="3" maxlength="30"
                   value="{{ $profile->username() }}">
            <span class="muted" style="font-size:.8rem">{{ trans('forum::messages.username_hint') }}</span>
        </div>
        <div class="field">
            <label for="bio">{{ trans('forum::messages.bio') }}</label>
            <textarea id="bio" name="bio" style="min-height:5rem">{{ $profile->bio() }}</textarea>
        </div>
        <div class="field">
            <label for="signature">{{ trans('forum::messages.signature') }}</label>
            <input type="text" id="signature" name="signature" maxlength="300" value="{{ $profile->signature() }}">
        </div>
        <button class="forum-btn" type="submit">{{ trans('forum::messages.save') }}</button>
        <a class="forum-btn gray" href="/members/{{ $profile->username() }}">{{ trans('forum::messages.cancel') }}</a>
    </form>
</div>
@endsection
