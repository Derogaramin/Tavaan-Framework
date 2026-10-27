@extends('core-user::auth.layout')

@section('content')
<h1>{{ trans('core-user::messages.ui.profile_title') }}</h1>

@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $fieldErrors)
            @foreach((array) $fieldErrors as $err)
                <div>{{ $err }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<form method="post" action="/profile">
    @csrf
    <div class="field">
        <label for="name">{{ trans('core-user::messages.ui.name') }}</label>
        <input type="text" id="name" name="name" value="{{ $old['name'] ?? $user->name }}" required>
    </div>
    <div class="field">
        <label>{{ trans('core-user::messages.ui.email') }}</label>
        <input type="email" value="{{ $user->email }}" disabled>
    </div>
    <div class="field">
        <label for="mobile">{{ trans('core-user::messages.ui.mobile') }}</label>
        <input type="tel" id="mobile" name="mobile" value="{{ $old['mobile'] ?? ($user->mobile ?? '') }}" dir="ltr">
    </div>
    <div class="field">
        <label for="bio">{{ trans('core-user::messages.ui.bio') }}</label>
        <textarea id="bio" name="bio" rows="3">{{ $old['bio'] ?? ($user->bio ?? '') }}</textarea>
    </div>
    <button class="btn" type="submit">{{ trans('core-user::messages.ui.save') }}</button>
</form>

<hr style="border:0;border-top:1px solid var(--b);margin:1.5rem 0">

<h1 style="font-size:1.05rem">{{ trans('core-user::messages.ui.change_password') }}</h1>
<form method="post" action="/profile/password">
    @csrf
    <div class="field">
        <label for="current_password">{{ trans('core-user::messages.ui.current_password') }}</label>
        <input type="password" id="current_password" name="current_password" required>
    </div>
    <div class="field">
        <label for="password">{{ trans('core-user::messages.ui.new_password') }}</label>
        <input type="password" id="password" name="password" required>
        <div class="hint">{{ trans('core-user::messages.ui.password_hint') }}</div>
    </div>
    <div class="field">
        <label for="password_confirmation">{{ trans('core-user::messages.ui.confirm_password') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button class="btn gray" type="submit">{{ trans('core-user::messages.ui.change_password') }}</button>
</form>

<div class="alt" style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
    <a class="btn small gray" href="/admin">{{ trans('core-user::messages.ui.dashboard') }}</a>
    <form method="post" action="/logout" style="margin:0">
        @csrf
        <button class="btn small red" type="submit">{{ trans('core-user::messages.ui.logout') }}</button>
    </form>
</div>
@endsection
