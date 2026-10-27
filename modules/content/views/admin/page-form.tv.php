@extends('content::admin.layout')

@section('content')
@php
    $isEdit = isset($page) && $page !== null;
    $old = is_array($old ?? null) ? $old : [];
    $value = static function (string $field, string $fallback = '') use ($isEdit, $page, $old): string {
        if (isset($old[$field]) && $old[$field] !== null) {
            return (string) $old[$field];
        }
        return $isEdit ? $fallback : '';
    };
    $commentsOpen = isset($old['comments_open'])
        ? (bool) $old['comments_open']
        : ($isEdit ? $page->commentsOpenFlag() : true);
@endphp

@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $field => $messages)
            @foreach((array) $messages as $message)
                <div>• {{ $message }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="card">
    <h2>{{ $isEdit ? trans('content::messages.edit_page') : trans('content::messages.new_page') }}</h2>

    <form method="post" action="{{ $isEdit ? '/admin/pages/' . $page->id() : '/admin/pages' }}">
        @csrf

        <div class="field">
            <label for="title">{{ trans('content::messages.field_title') }} *</label>
            <input type="text" id="title" name="title" required maxlength="255"
                   value="{{ $value('title', $isEdit ? $page->title() : '') }}">
        </div>

        <div class="field">
            <label for="slug">{{ trans('content::messages.field_slug') }}</label>
            <input type="text" id="slug" name="slug" maxlength="200" dir="auto"
                   value="{{ $value('slug', $isEdit ? $page->slug() : '') }}"
                   placeholder="{{ trans('content::messages.slug_auto_hint') }}">
        </div>

        <div class="field">
            <label for="body">{{ trans('content::messages.field_body') }} (Markdown)</label>
            <textarea id="body" name="body" dir="auto">{{ $value('body', $isEdit ? (string) ($page->body() ?? '') : '') }}</textarea>
            <p class="muted">{{ trans('content::messages.body_hint') }}</p>
        </div>

        <div class="field">
            <label for="excerpt">{{ trans('content::messages.field_excerpt') }}</label>
            <textarea id="excerpt" name="excerpt" style="min-height:4rem">{{ $value('excerpt', $isEdit ? (string) ($page->excerpt() ?? '') : '') }}</textarea>
            <p class="muted">{{ trans('content::messages.excerpt_hint') }}</p>
        </div>

        <div class="field checkline">
            <input type="hidden" name="comments_open" value="0">
            <input type="checkbox" id="comments_open" name="comments_open" value="1" style="width:auto" {{ $commentsOpen ? 'checked' : '' }}>
            <label for="comments_open">{{ trans('content::messages.field_comments_open') }}</label>
        </div>

        @if(!$isEdit)
        <div class="field">
            <label for="status">{{ trans('content::messages.status_col') }}</label>
            <select id="status" name="status">
                <option value="draft">{{ trans('content::status.draft') }}</option>
                <option value="published">{{ trans('content::status.published') }}</option>
            </select>
        </div>
        @endif

        <fieldset style="border:1px solid var(--b);border-radius:8px;margin-block-end:1rem">
            <legend>{{ trans('content::messages.seo') }}</legend>
            <div class="field">
                <label for="seo_title">{{ trans('content::messages.field_seo_title') }}</label>
                <input type="text" id="seo_title" name="seo_title" maxlength="255"
                       value="{{ $value('seo_title', $isEdit ? $page->seo()->title : '') }}">
            </div>
            <div class="field">
                <label for="seo_description">{{ trans('content::messages.field_seo_description') }}</label>
                <textarea id="seo_description" name="seo_description" style="min-height:3.5rem">{{ $value('seo_description', $isEdit ? $page->seo()->description : '') }}</textarea>
            </div>
            <div class="field">
                <label for="seo_keywords">{{ trans('content::messages.field_seo_keywords') }}</label>
                <input type="text" id="seo_keywords" name="seo_keywords" maxlength="500"
                       value="{{ $value('seo_keywords', $isEdit ? $page->seo()->keywords : '') }}">
            </div>
        </fieldset>

        <button class="btn" type="submit">{{ $isEdit ? trans('content::messages.save') : trans('content::messages.create') }}</button>
        <a class="btn gray" href="/admin/pages">{{ trans('content::messages.back_to_list') }}</a>
    </form>

    @if($isEdit && !$page->status()->isPublic())
    <form method="post" action="/admin/pages/{{ $page->id() }}/publish" style="margin-block-start:1rem">
        @csrf
        <button class="btn green" type="submit">{{ trans('content::messages.publish') }}</button>
    </form>
    @endif

    @if($isEdit && $page->status()->isPublic())
    <form method="post" action="/admin/pages/{{ $page->id() }}/archive" style="margin-block-start:1rem">
        @csrf
        <button class="btn gray" type="submit">{{ trans('content::messages.archive_action') }}</button>
    </form>
    @endif

    @if($isEdit)
    <form method="post" action="/admin/pages/{{ $page->id() }}" style="margin-block-start:1rem">
        @csrf
        @method('DELETE')
        <button class="btn red" type="submit" onclick="return confirm('{{ trans('content::messages.confirm_delete') }}')">{{ trans('content::messages.delete') }}</button>
    </form>
    @endif
</div>
@endsection
