@extends('core-user::auth.layout')

@section('content')
<h1>{{ trans('core-user::messages.ui.register_title') }}</h1>

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $fieldErrors)
            @foreach((array) $fieldErrors as $err)
                <div>{{ $err }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<form method="post" action="/register">
    @csrf
    <div class="field">
        <label for="name">{{ trans('core-user::messages.ui.name') }}</label>
        <input type="text" id="name" name="name" value="{{ $old['name'] ?? '' }}" required autofocus>
    </div>
    <div class="field">
        <label for="email">{{ trans('core-user::messages.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ $old['email'] ?? '' }}" required>
    </div>
    <div class="field">
        <label for="mobile">{{ trans('core-user::messages.ui.mobile') }}</label>
        <input type="tel" id="mobile" name="mobile" value="{{ $old['mobile'] ?? '' }}" dir="ltr">
    </div>
    <div class="field">
        <label for="password">{{ trans('core-user::messages.ui.password') }}</label>
        <input type="password" id="password" name="password" required>
        <div class="hint">{{ trans('core-user::messages.ui.password_hint') }}</div>
    </div>
    <div class="field">
        <label for="password_confirmation">{{ trans('core-user::messages.ui.confirm_password') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button class="btn" type="submit">{{ trans('core-user::messages.ui.register') }}</button>
</form>

<div class="alt">
    {{ trans('core-user::messages.ui.have_account') }}
    <a href="/login">{{ trans('core-user::messages.ui.login') }}</a>
</div>
@endsection
