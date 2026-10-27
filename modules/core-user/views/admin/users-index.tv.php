@extends('core-user::admin.layout')

@section('content')
@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@php $errors = \Tavaan\Core\Facade\Facades\Session::pull('core_user_admin_errors') ?? []; @endphp
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
    <h2>{{ trans('core-user::messages.ui.admin_title') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/users" class="toolbar">
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ trans('core-user::messages.ui.search_placeholder') }}">
        <select name="status" style="max-width:160px">
            <option value="">{{ trans('core-user::messages.ui.all_statuses') }}</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ $status === $s->value ? 'selected' : '' }}>{{ trans('core-user::messages.status.' . $s->value) }}</option>
            @endforeach
        </select>
        <button class="btn gray" type="submit">{{ trans('core-user::messages.ui.search_placeholder') }}</button>
        <a class="btn" href="/admin/users/create">+ {{ trans('core-user::messages.ui.new_user') }}</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ trans('core-user::messages.ui.col_name') }}</th>
                <th>{{ trans('core-user::messages.ui.col_email') }}</th>
                <th>{{ trans('core-user::messages.ui.col_mobile') }}</th>
                <th>{{ trans('core-user::messages.ui.col_status') }}</th>
                <th>{{ trans('core-user::messages.ui.col_created') }}</th>
                <th>{{ trans('core-user::messages.ui.col_last_login') }}</th>
                <th>{{ trans('core-user::messages.ui.col_actions') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $u)
            <tr>
                <td>{{ trans_number($u->id) }}</td>
                <td>
                    <a href="/admin/users/{{ $u->id }}/edit">{{ $u->name }}</a>
                    @if($u->id === $currentUserId)<span class="muted">{{ trans('core-user::messages.ui.you') }}</span>@endif
                </td>
                <td dir="ltr">{{ $u->email }}</td>
                <td dir="ltr">{{ $u->mobile ?? '—' }}</td>
                <td><span class="badge {{ $u->status->value }}">{{ trans('core-user::messages.status.' . $u->status->value) }}</span></td>
                <td>{{ $u->createdAt !== null ? jalali($u->createdAt, 'Y/m/d') : '—' }}</td>
                <td>{{ $u->lastLoginAt !== null ? jalali($u->lastLoginAt, 'Y/m/d H:i') : '—' }}</td>
                <td class="row-actions">
                    <a class="btn gray" href="/admin/users/{{ $u->id }}/edit">{{ trans('core-user::messages.ui.edit') }}</a>
                    @if($u->id !== $currentUserId)
                        @if($u->status->value !== 'active')
                        <form class="inline" method="post" action="/admin/users/{{ $u->id }}/status">
                            @csrf
                            <input type="hidden" name="status" value="active">
                            <button class="btn green" type="submit">{{ trans('core-user::messages.ui.activate') }}</button>
                        </form>
                        @else
                        <form class="inline" method="post" action="/admin/users/{{ $u->id }}/status">
                            @csrf
                            <input type="hidden" name="status" value="inactive">
                            <button class="btn amber" type="submit">{{ trans('core-user::messages.ui.deactivate') }}</button>
                        </form>
                        @endif
                        <form class="inline" method="post" action="/admin/users/{{ $u->id }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn red" type="submit" onclick="return confirm('{{ trans('core-user::messages.ui.confirm_delete') }}')">{{ trans('core-user::messages.ui.delete') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">{{ trans('core-user::messages.ui.no_users') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $pageUrl = static function (int $p) use ($queryString): string {
            $params = [];
            parse_str($queryString, $params);
            $params['page'] = $p;
            return '/admin/users?' . http_build_query($params);
        };
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @if($page > 1)<a href="{{ $pageUrl($page - 1) }}">‹</a>@endif
        @for($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="{{ $pageUrl($p) }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
        @if($page < $lastPage)<a href="{{ $pageUrl($page + 1) }}">›</a>@endif
    </nav>
    @endif
</div>
@endsection
