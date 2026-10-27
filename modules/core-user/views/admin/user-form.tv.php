@extends('core-user::admin.layout')

@section('content')
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

<div class="card">
    <h2>{{ $user === null ? trans('core-user::messages.ui.create_user') : trans('core-user::messages.ui.edit_user') . ' — ' . $user->name }}</h2>

    <form method="post" action="{{ $user === null ? '/admin/users' : '/admin/users/' . $user->id }}">
        @csrf
        <div class="grid2">
            <div class="field">
                <label for="name">{{ trans('core-user::messages.ui.name') }}</label>
                <input type="text" id="name" name="name" value="{{ $old['name'] ?? ($user->name ?? '') }}" required>
            </div>
            <div class="field">
                <label for="email">{{ trans('core-user::messages.ui.email') }}</label>
                <input type="email" id="email" name="email" value="{{ $old['email'] ?? ($user->email ?? '') }}" dir="ltr" required>
            </div>
            <div class="field">
                <label for="mobile">{{ trans('core-user::messages.ui.mobile') }}</label>
                <input type="tel" id="mobile" name="mobile" value="{{ $old['mobile'] ?? ($user->mobile ?? '') }}" dir="ltr">
            </div>
            <div class="field">
                <label for="status">{{ trans('core-user::messages.ui.status') }}</label>
                <select id="status" name="status">
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}" {{ ($old['status'] ?? ($user->status->value ?? 'active')) === $s->value ? 'selected' : '' }}>{{ trans('core-user::messages.status.' . $s->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($user === null)
        <div class="field">
            <label for="password">{{ trans('core-user::messages.ui.password') }}</label>
            <input type="password" id="password" name="password" required>
            <div class="hint muted">{{ trans('core-user::messages.ui.password_hint') }}</div>
        </div>
        @endif

        <div class="field">
            <label for="bio">{{ trans('core-user::messages.ui.bio') }}</label>
            <textarea id="bio" name="bio" rows="3">{{ $old['bio'] ?? ($user->bio ?? '') }}</textarea>
        </div>

        @if(!empty($availableRoles))
        <div class="field">
            <label>{{ trans('core-user::messages.ui.roles') }}</label>
            <div>
                @foreach($availableRoles as $role)
                    <label class="role-chip">
                        <input type="checkbox" name="roles[]" value="{{ $role['key'] }}" {{ in_array($role['key'], $userRoles, true) ? 'checked' : '' }} style="width:auto">
                        {{ $role['name'] }} <span class="muted">({{ $role['key'] }})</span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="row-actions">
            <button class="btn" type="submit">{{ trans('core-user::messages.ui.save') }}</button>
            <a class="btn gray" href="/admin/users">{{ trans('core-user::messages.ui.admin_title') }}</a>
        </div>
    </form>
</div>

@if($user !== null)
<div class="card">
    <h3>{{ trans('core-user::messages.ui.reset_password') }}</h3>
    <form method="post" action="/admin/users/{{ $user->id }}/password" class="toolbar">
        @csrf
        <input type="password" name="password" placeholder="{{ trans('core-user::messages.ui.reset_password_hint') }}" required style="max-width:320px">
        <button class="btn amber" type="submit">{{ trans('core-user::messages.ui.reset_password') }}</button>
    </form>
</div>
@endif
@endsection
