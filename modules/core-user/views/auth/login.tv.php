@extends('core-user::auth.layout')

@section('content')
<h1>{{ trans('core-user::messages.ui.login_title') }}</h1>

@php $msg = $message ?? null; @endphp
@if(is_string($msg) && $msg !== '')
    <div class="flash-ok">{{ $msg }}</div>
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

<form method="post" action="/login">
    @csrf
    <div class="field">
        <label for="email">{{ trans('core-user::messages.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ $old['email'] ?? '' }}" required autofocus>
    </div>
    <div class="field">
        <label for="password">{{ trans('core-user::messages.ui.password') }}</label>
        <input type="password" id="password" name="password" required>
    </div>
    <label class="remember">
        <input type="checkbox" name="remember" value="1">
        {{ trans('core-user::messages.ui.remember_me') }}
    </label>
    <button class="btn" type="submit">{{ trans('core-user::messages.ui.login') }}</button>
</form>

@if(!empty($registrationEnabled))
<div class="alt">
    {{ trans('core-user::messages.ui.no_account') }}
    <a href="/register">{{ trans('core-user::messages.ui.register') }}</a>
</div>
@endif
@endsection
