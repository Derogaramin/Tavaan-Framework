@extends('blog::admin.layout')

@section('content')
@php
    $ok = \Tavaan\Core\Facade\Facades\Session::pull('blog_post_ok');
    $isEdit = isset($post) && $post !== null;
    $old = is_array($old ?? null) ? $old : [];
    $value = static function (string $field, string $fallback = '') use ($isEdit, $post, $old): string {
        if (isset($old[$field]) && $old[$field] !== null) {
            return (string) $old[$field];
        }
        return $isEdit ? $fallback : '';
    };
@endphp

@if(is_string($ok) && $ok !== '')
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
    <h2>{{ $isEdit ? trans('blog::messages.edit_post') : trans('blog::messages.new_post') }}</h2>

    <form method="post" action="{{ $isEdit ? '/admin/blog/posts/' . $post->id() : '/admin/blog/posts' }}">
        @csrf

        <div class="field">
            <label for="title">{{ trans('blog::messages.field_title') }} *</label>
            <input type="text" id="title" name="title" required maxlength="255"
                   value="{{ $value('title', $isEdit ? $post->title() : '') }}">
        </div>

        <div class="grid2">
            <div class="field">
                <label for="slug">{{ trans('blog::messages.field_slug') }}</label>
                <input type="text" id="slug" name="slug" maxlength="200" dir="auto"
                       value="{{ $value('slug', $isEdit ? $post->slug() : '') }}"
                       placeholder="{{ trans('blog::messages.slug_auto_hint') }}">
            </div>
            <div class="field">
                <label for="category_id">{{ trans('blog::messages.field_category') }}</label>
                <select id="category_id" name="category_id">
                    <option value="">{{ trans('blog::messages.no_category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id() }}"
                            {{ (string) ($old['category_id'] ?? ($isEdit ? (string) ($post->categoryId() ?? '') : '')) === (string) $category->id() ? 'selected' : '' }}>
                            {{ $category->name() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label for="body">{{ trans('blog::messages.field_body') }} (Markdown)</label>
            <textarea id="body" name="body" dir="auto">{{ $value('body', $isEdit ? (string) ($post->body() ?? '') : '') }}</textarea>
            <p class="muted">{{ trans('blog::messages.body_hint') }}</p>
        </div>

        <div class="field">
            <label for="excerpt">{{ trans('blog::messages.field_excerpt') }}</label>
            <textarea id="excerpt" name="excerpt" style="min-height:4rem">{{ $value('excerpt', $isEdit ? (string) ($post->excerpt() ?? '') : '') }}</textarea>
        </div>

        <div class="grid2">
            <div class="field">
                <label for="tags">{{ trans('blog::messages.field_tags') }}</label>
                <input type="text" id="tags" name="tags" value="{{ implode('، ', $postTags ?? []) }}" placeholder="{{ trans('blog::messages.tags_hint') }}">
            </div>
            <div class="field">
                <label for="featured_image">{{ trans('blog::messages.field_featured_image') }}</label>
                <input type="text" id="featured_image" name="featured_image" maxlength="26" dir="ltr"
                       value="{{ $value('featured_image', $isEdit ? (string) ($post->featuredImage() ?? '') : '') }}"
                       placeholder="ULID (tvn_media)">
            </div>
        </div>

        @if(!$isEdit)
        <div class="grid2">
            <div class="field">
                <label for="status">{{ trans('blog::messages.field_status') }}</label>
                <select id="status" name="status">
                    <option value="draft">{{ trans('content::status.draft') }}</option>
                    <option value="published">{{ trans('content::status.published') }}</option>
                    <option value="scheduled">{{ trans('content::status.scheduled') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="published_at">{{ trans('blog::messages.field_publish_at') }}</label>
                <input type="datetime-local" id="published_at" name="published_at">
            </div>
        </div>
        @endif

        <fieldset style="border:1px solid var(--b);border-radius:8px;margin-block-end:1rem">
            <legend>{{ trans('blog::messages.seo') }}</legend>
            <div class="field">
                <label for="seo_title">{{ trans('blog::messages.field_seo_title') }}</label>
                <input type="text" id="seo_title" name="seo_title" maxlength="255"
                       value="{{ $value('seo_title', $isEdit ? $post->seo()->title : '') }}">
            </div>
            <div class="field">
                <label for="seo_description">{{ trans('blog::messages.field_seo_description') }}</label>
                <textarea id="seo_description" name="seo_description" style="min-height:3.5rem">{{ $value('seo_description', $isEdit ? $post->seo()->description : '') }}</textarea>
            </div>
            <div class="field">
                <label for="seo_keywords">{{ trans('blog::messages.field_seo_keywords') }}</label>
                <input type="text" id="seo_keywords" name="seo_keywords" maxlength="500"
                       value="{{ $value('seo_keywords', $isEdit ? $post->seo()->keywords : '') }}">
            </div>
        </fieldset>

        <button class="btn" type="submit">{{ $isEdit ? trans('blog::messages.save') : trans('blog::messages.create') }}</button>
        <a class="btn gray" href="/admin/blog/posts">{{ trans('blog::messages.back_to_list') }}</a>
    </form>

    @if($isEdit && !$post->status()->isPublic())
    <form method="post" action="/admin/blog/posts/{{ $post->id() }}/publish" style="margin-block-start:1rem">
        @csrf
        <label for="publish_at">{{ trans('blog::messages.field_publish_at') }} ({{ trans('blog::messages.schedule_hint') }})</label>
        <input type="datetime-local" id="publish_at" name="published_at" style="max-width:20rem">
        <button class="btn green" type="submit" style="margin-block-start:.5rem">{{ trans('blog::messages.publish') }}</button>
    </form>
    @endif

    @if($isEdit && $post->status()->isPublic())
    <form method="post" action="/admin/blog/posts/{{ $post->id() }}/archive" style="margin-block-start:1rem">
        @csrf
        <button class="btn gray" type="submit">{{ trans('blog::messages.archive_action') }}</button>
    </form>
    @endif
</div>
@endsection
