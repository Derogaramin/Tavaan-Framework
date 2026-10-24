@extends('news::admin.layout')

@section('content')
@php
    $ok = \Tavaan\Core\Facade\Facades\Session::pull('news_article_ok');
    $isEdit = isset($article) && $article !== null;
    $old = is_array($old ?? null) ? $old : [];
    $value = static function (string $field, string $fallback = '') use ($isEdit, $article, $old): string {
        if (isset($old[$field]) && $old[$field] !== null) {
            return (string) $old[$field];
        }
        return $isEdit ? $fallback : '';
    };
    $canApprove = $canApprove ?? false;
    $canPublish = $canPublish ?? false;
    $canSubmit = $canSubmit ?? false;
    $canBreaking = $canBreaking ?? false;
    $statusValue = $isEdit ? $article->status()->value : 'draft';
@endphp

@if(is_string($ok) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif
@php $err = \Tavaan\Core\Facade\Facades\Session::pull('news_article_error'); @endphp
@if(is_string($err) && $err !== '')
    <div class="flash-err">{{ $err }}</div>
@endif

@if(!empty($errors))
    <div class="errors">
        @foreach($errors as $messages)
            @foreach((array) $messages as $message)
                <div>• {{ $message }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<div class="card">
    <h2>
        {{ $isEdit ? trans('news::messages.edit_article') : trans('news::messages.new_article') }}
        @if($isEdit)
            <span class="badge {{ $statusValue }}">{{ trans('news::status.' . $statusValue) }}</span>
            @if($article->isBreaking())<span class="badge archived">{{ trans('news::messages.breaking') }}</span>@endif
        @endif
    </h2>

    @if($isEdit && $article->rejectReason() !== null)
        <div class="flash-err">{{ trans('news::messages.reject_reason', ['reason' => $article->rejectReason()]) }}</div>
    @endif

    <form method="post" action="{{ $isEdit ? '/admin/news/articles/' . $article->id() : '/admin/news/articles' }}">
        @csrf

        <div class="field">
            <label for="title">{{ trans('news::messages.field_title') }} *</label>
            <input type="text" id="title" name="title" required maxlength="300"
                   value="{{ $value('title', $isEdit ? $article->title() : '') }}">
        </div>

        <div class="grid2">
            <div class="field">
                <label for="slug">{{ trans('news::messages.field_slug') }}</label>
                <input type="text" id="slug" name="slug" maxlength="255" dir="auto"
                       value="{{ $value('slug', $isEdit ? $article->slug() : '') }}"
                       placeholder="{{ trans('news::messages.slug_auto_hint') }}">
            </div>
            <div class="field">
                <label for="category_id">{{ trans('news::messages.field_category') }}</label>
                <select id="category_id" name="category_id">
                    <option value="">{{ trans('news::messages.no_category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id() }}"
                            {{ (string) ($old['category_id'] ?? ($isEdit ? (string) ($article->categoryId() ?? '') : '')) === (string) $category->id() ? 'selected' : '' }}>
                            {{ $category->title() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label for="lead">{{ trans('news::messages.field_lead') }}</label>
            <textarea id="lead" name="lead" style="min-height:4rem" maxlength="2000">{{ $value('lead', $isEdit ? (string) ($article->lead() ?? '') : '') }}</textarea>
        </div>

        <div class="field">
            <label for="body">{{ trans('news::messages.field_body') }} (Markdown)</label>
            <textarea id="body" name="body" dir="auto">{{ $value('body', $isEdit ? (string) ($article->body() ?? '') : '') }}</textarea>
        </div>

        <div class="grid2">
            <div class="field">
                <label for="tags">{{ trans('news::messages.field_tags') }}</label>
                <input type="text" id="tags" name="tags" value="{{ implode('، ', $articleTags ?? []) }}">
            </div>
            <div class="field">
                <label for="featured_image">{{ trans('news::messages.field_featured_image') }}</label>
                <input type="text" id="featured_image" name="featured_image" maxlength="26" dir="ltr"
                       value="{{ $value('featured_image', $isEdit ? (string) ($article->featuredImage() ?? '') : '') }}">
            </div>
        </div>

        <div class="field">
            <label for="gallery">{{ trans('news::messages.field_gallery') }}</label>
            <input type="text" id="gallery" name="gallery" dir="ltr"
                   value="{{ $isEdit ? implode(',', $article->gallery()) : '' }}"
                   placeholder="ULID,ULID,…">
        </div>

        <fieldset style="border:1px solid var(--b);border-radius:8px;margin-block-end:1rem">
            <legend>{{ trans('news::messages.seo') }}</legend>
            <div class="field">
                <label for="seo_title">{{ trans('news::messages.field_seo_title') }}</label>
                <input type="text" id="seo_title" name="seo_title" maxlength="300"
                       value="{{ $value('seo_title', $isEdit ? $article->seo()->title : '') }}">
            </div>
            <div class="field">
                <label for="seo_description">{{ trans('news::messages.field_seo_description') }}</label>
                <textarea id="seo_description" name="seo_description" style="min-height:3.5rem">{{ $value('seo_description', $isEdit ? $article->seo()->description : '') }}</textarea>
            </div>
        </fieldset>

        <button class="btn" type="submit">{{ $isEdit ? trans('news::messages.save') : trans('news::messages.create') }}</button>
        <a class="btn gray" href="/admin/news/articles">{{ trans('news::messages.back_to_list') }}</a>
    </form>

    @if($isEdit)
    <div class="workflow">
        <strong>{{ trans('news::messages.workflow') }}:</strong>

        @if($canSubmit && $statusValue === 'draft')
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/submit">
            @csrf
            <button class="btn amber" type="submit">{{ trans('news::messages.submit_for_review') }}</button>
        </form>
        @endif

        @if($canApprove && $statusValue === 'review')
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/approve" style="display:inline-flex;gap:.4rem;align-items:center">
            @csrf
            <input type="datetime-local" name="scheduled_at" title="{{ trans('news::messages.field_schedule_at') }}" style="width:auto">
            <button class="btn green" type="submit">{{ trans('news::messages.approve') }}</button>
        </form>
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/reject" style="display:inline-flex;gap:.4rem;align-items:center">
            @csrf
            <input type="text" name="reason" placeholder="{{ trans('news::messages.reject_reason_placeholder') }}" style="width:auto">
            <button class="btn red" type="submit">{{ trans('news::messages.reject') }}</button>
        </form>
        @endif

        @if($canPublish && $statusValue === 'approved')
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/publish">
            @csrf
            <button class="btn blue" type="submit">{{ trans('news::messages.publish_now') }}</button>
        </form>
        @endif

        @if($canBreaking && ($statusValue === 'published' || $statusValue === 'approved'))
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/breaking">
            @csrf
            <input type="hidden" name="value" value="{{ $article->isBreaking() ? '0' : '1' }}">
            <button class="btn red" type="submit">
                {{ $article->isBreaking() ? trans('news::messages.unmark_breaking') : trans('news::messages.mark_breaking') }}
            </button>
        </form>
        @endif

        @if($canPublish && $statusValue === 'published')
        <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}/archive">
            @csrf
            <button class="btn gray" type="submit">{{ trans('news::messages.archive_action') }}</button>
        </form>
        @endif
    </div>
    @endif
</div>
@endsection
