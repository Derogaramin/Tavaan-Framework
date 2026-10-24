@extends('news::admin.layout')

@section('content')
@php
    $ok = \Tavaan\Core\Facade\Facades\Session::pull('news_category_ok');
    $err = \Tavaan\Core\Facade\Facades\Session::pull('news_category_error');
    $byId = [];
    foreach ($categories as $c) { if ($c->id() !== null) { $byId[$c->id()] = $c; } }
@endphp
@if(is_string($ok) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif
@if(is_string($err) && $err !== '')
    <div class="flash-err">{{ $err }}</div>
@endif

<div class="card">
    <h2>{{ trans('news::messages.admin_categories') }} <span class="muted">({{ trans_number(count($categories)) }})</span></h2>

    <form method="post" action="/admin/news/categories" class="news-form" style="margin-block-end:1.2rem">
        @csrf
        <div class="grid2">
            <div class="field">
                <label for="title">{{ trans('news::messages.field_category_title') }} *</label>
                <input type="text" id="title" name="title" required maxlength="190">
            </div>
            <div class="field">
                <label for="slug">{{ trans('news::messages.field_slug') }}</label>
                <input type="text" id="slug" name="slug" maxlength="200" dir="auto">
            </div>
        </div>
        <div class="grid2">
            <div class="field">
                <label for="parent_id">{{ trans('news::messages.field_parent') }}</label>
                <select id="parent_id" name="parent_id">
                    <option value="">—</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id() }}">{{ $category->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="description">{{ trans('news::messages.field_description') }}</label>
                <input type="text" id="description" name="description">
            </div>
        </div>
        <button class="btn" type="submit">{{ trans('news::messages.create') }}</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('news::messages.field_category_title') }}</th>
                <th>slug</th>
                <th>{{ trans('news::messages.field_parent') }}</th>
                <th>{{ trans('news::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($categories as $category)
            <tr>
                <td>
                    <form class="inline" method="post" action="/admin/news/categories/{{ $category->id() }}" style="display:flex;gap:.4rem">
                        @csrf
                        <input type="text" name="title" value="{{ $category->title() }}" style="width:auto" required>
                        <input type="hidden" name="parent_id" value="{{ $category->parentId() ?? '' }}">
                        <button class="btn gray" type="submit">{{ trans('news::messages.save') }}</button>
                    </form>
                </td>
                <td dir="ltr">{{ $category->slug() }}</td>
                <td>{{ $category->parentId() !== null && isset($byId[$category->parentId()]) ? $byId[$category->parentId()]->title() : '—' }}</td>
                <td class="row-actions">
                    <a class="btn blue" href="/news/category/{{ $category->slug() }}" target="_blank">{{ trans('news::messages.view') }}</a>
                    <form class="inline" method="post" action="/admin/news/categories/{{ $category->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('news::messages.confirm_delete') }}')">{{ trans('news::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">{{ trans('news::messages.no_categories') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
