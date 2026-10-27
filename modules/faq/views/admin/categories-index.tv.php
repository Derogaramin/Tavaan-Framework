@extends('faq::admin.layout')

@section('content')
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
    <h2>{{ trans('faq::messages.new_category') }}</h2>
    @php $old = is_array($old ?? null) ? $old : []; @endphp
    <form method="post" action="/admin/faq-categories">
        @csrf
        <div class="grid2">
            <div class="field">
                <label for="new_title">{{ trans('faq::messages.field_title') }} *</label>
                <input type="text" id="new_title" name="title" required maxlength="255"
                       value="{{ isset($old['title']) ? (string) $old['title'] : '' }}">
            </div>
            <div class="field">
                <label for="new_slug">{{ trans('faq::messages.field_slug') }}</label>
                <input type="text" id="new_slug" name="slug" maxlength="200" dir="auto"
                       value="{{ isset($old['slug']) ? (string) $old['slug'] : '' }}"
                       placeholder="{{ trans('faq::messages.slug_auto_hint') }}">
            </div>
        </div>
        <div class="field" style="max-width:160px">
            <label for="new_sort">{{ trans('faq::messages.sort_col') }}</label>
            <input type="number" id="new_sort" name="sort_order" min="0" max="999999"
                   value="{{ isset($old['sort_order']) ? (string) $old['sort_order'] : '0' }}">
        </div>
        <button class="btn" type="submit">{{ trans('faq::messages.create') }}</button>
        <a class="btn gray" href="/admin/faqs">{{ trans('faq::messages.back_to_list') }}</a>
    </form>
</div>

<div class="card">
    <h2>{{ trans('faq::messages.categories_title') }} <span class="muted">({{ trans_number(count($categories)) }})</span></h2>

    <!-- فرم‌های ویرایش هر ردیف بیرون از جدول؛ ورودی‌ها با ویژگی form به آن‌ها متصل‌اند (HTML5). -->
    @foreach($categories as $fqCat)
        <form method="post" action="/admin/faq-categories/{{ $fqCat->id() }}" id="cat-edit-{{ $fqCat->id() }}">@csrf</form>
    @endforeach

    <table>
        <thead>
            <tr>
                <th>{{ trans('faq::messages.field_title') }}</th>
                <th>{{ trans('faq::messages.field_slug') }}</th>
                <th>{{ trans('faq::messages.category_count_col') }}</th>
                <th>{{ trans('faq::messages.sort_col') }}</th>
                <th>{{ trans('faq::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($categories as $fqCat)
            <tr>
                <td><input type="text" form="cat-edit-{{ $fqCat->id() }}" name="title" maxlength="255" value="{{ $fqCat->title() }}"></td>
                <td><input type="text" form="cat-edit-{{ $fqCat->id() }}" name="slug" maxlength="200" dir="auto" value="{{ $fqCat->slug() }}"></td>
                <td>{{ trans_number((int) ($counts[$fqCat->id()] ?? 0)) }}</td>
                <td><input type="number" form="cat-edit-{{ $fqCat->id() }}" name="sort_order" min="0" max="999999" value="{{ $fqCat->sortOrder() }}" style="max-width:90px"></td>
                <td class="row-actions">
                    <button class="btn sm" type="submit" form="cat-edit-{{ $fqCat->id() }}">{{ trans('faq::messages.save') }}</button>
                    <a class="btn gray sm" href="/admin/faqs?category_id={{ $fqCat->id() }}">{{ trans('faq::messages.category_count_col') }}</a>
                    <form class="inline" method="post" action="/admin/faq-categories/{{ $fqCat->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red sm" type="submit" onclick="return confirm('{{ trans('faq::messages.confirm_delete') }}')">{{ trans('faq::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ trans('faq::messages.no_categories') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
