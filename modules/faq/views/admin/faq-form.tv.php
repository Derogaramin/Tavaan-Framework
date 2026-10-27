@extends('faq::admin.layout')

@section('content')
@php
    $isEdit = isset($item) && $item !== null;
    $old = is_array($old ?? null) ? $old : [];

    $value = static function (string $field, string $fallback = '') use ($isEdit, $item, $old): string {
        if (isset($old[$field]) && $old[$field] !== null) {
            return (string) $old[$field];
        }
        return $isEdit ? $fallback : '';
    };
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
    <h2>{{ $isEdit ? trans('faq::messages.edit_faq') : trans('faq::messages.new_faq') }}</h2>

    <form method="post" action="{{ $isEdit ? '/admin/faqs/' . $item->id() : '/admin/faqs' }}">
        @csrf

        <div class="field">
            <label for="question">{{ trans('faq::messages.field_question') }} *</label>
            <input type="text" id="question" name="question" required maxlength="500"
                   value="{{ $value('question', $isEdit ? $item->question() : '') }}">
        </div>

        <div class="field">
            <label for="answer">{{ trans('faq::messages.field_answer') }} *</label>
            <textarea id="answer" name="answer" rows="10" dir="auto">{{ $value('answer', $isEdit ? $item->answer() : '') }}</textarea>
            <p class="muted">{{ trans('faq::messages.answer_help') }}</p>
        </div>

        <div class="grid2">
            <div class="field">
                <label for="category_id">{{ trans('faq::messages.field_category') }}</label>
                @php
                    $selectedCat = isset($old['category_id'])
                        ? (string) $old['category_id']
                        : ($isEdit ? (string) ($item->categoryId() ?? '') : '');
                @endphp
                <select id="category_id" name="category_id">
                    <option value="">{{ trans('faq::messages.uncategorized') }}</option>
                    @foreach($categories as $fqCat)
                        <option value="{{ $fqCat->id() }}" {{ $selectedCat === $fqCat->id() ? 'selected' : '' }}>{{ $fqCat->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="sort_order">{{ trans('faq::messages.field_sort') }}</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="999999"
                       value="{{ $value('sort_order', $isEdit ? (string) $item->sortOrder() : '0') }}">
                <p class="muted">{{ trans('faq::messages.sort_hint') }}</p>
            </div>
        </div>

        @if(!$isEdit)
        <div class="field">
            <label for="status">{{ trans('faq::messages.field_status') }}</label>
            <select id="status" name="status">
                <option value="draft">{{ trans('faq::status.draft') }}</option>
                <option value="published" {{ (isset($old['status']) && (string) $old['status'] === 'published') ? 'selected' : '' }}>{{ trans('faq::status.published') }}</option>
            </select>
        </div>
        @endif

        <button class="btn" type="submit">{{ $isEdit ? trans('faq::messages.save') : trans('faq::messages.create') }}</button>
        <a class="btn gray" href="/admin/faqs">{{ trans('faq::messages.back_to_list') }}</a>
    </form>

    @if($isEdit && !$item->isTrashed() && $item->status()->value === 'draft')
    <form method="post" action="/admin/faqs/{{ $item->id() }}/publish" style="margin-block-start:1rem">
        @csrf
        <button class="btn green" type="submit">{{ trans('faq::messages.publish_action') }}</button>
    </form>
    @endif

    @if($isEdit && !$item->isTrashed() && $item->status()->value === 'published')
    <form method="post" action="/admin/faqs/{{ $item->id() }}/archive" style="margin-block-start:1rem">
        @csrf
        <button class="btn" type="submit">{{ trans('faq::messages.archive_action') }}</button>
    </form>
    @endif

    @if($isEdit && !$item->isTrashed() && $item->status()->value === 'archived')
    <form method="post" action="/admin/faqs/{{ $item->id() }}/draft" style="margin-block-start:1rem">
        @csrf
        <button class="btn" type="submit">{{ trans('faq::messages.draft_action') }}</button>
    </form>
    @endif

    @if($isEdit && $item->isVisible())
    <p style="margin-block-start:1rem">
        <a class="btn gray" href="/faq#q-{{ $item->id() }}" target="_blank">{{ trans('faq::messages.show_faq') }}</a>
    </p>
    @endif

    @if($isEdit)
    <form method="post" action="/admin/faqs/{{ $item->id() }}" style="margin-block-start:1rem">
        @csrf
        @method('DELETE')
        @if($item->isTrashed())
            <button class="btn red" type="submit" onclick="return confirm('{{ trans('faq::messages.confirm_delete_forever') }}')">{{ trans('faq::messages.delete_forever') }}</button>
        @else
            <button class="btn red" type="submit" onclick="return confirm('{{ trans('faq::messages.confirm_delete') }}')">{{ trans('faq::messages.delete') }}</button>
        @endif
    </form>
    @endif
</div>
@endsection
