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
    <h2>{{ trans('faq::messages.admin_faqs') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/faqs" class="filters">
        <div class="field">
            <label for="status">{{ trans('faq::messages.status_col') }}</label>
            <select id="status" name="status">
                <option value="">{{ trans('faq::messages.all_statuses') }}</option>
                @foreach(['draft', 'published', 'archived'] as $s)
                    <option value="{{ $s }}" {{ ($status ?? '') === $s ? 'selected' : '' }}>{{ trans('faq::status.' . $s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="category_id">{{ trans('faq::messages.category_col') }}</label>
            <select id="category_id" name="category_id">
                <option value="">{{ trans('faq::messages.all_categories') }}</option>
                @foreach($categories as $fqCat)
                    <option value="{{ $fqCat->id() }}" {{ ($categoryId ?? '') === $fqCat->id() ? 'selected' : '' }}>{{ $fqCat->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:1 1 200px">
            <label for="q">{{ trans('faq::messages.search_label') }}</label>
            <input type="search" id="q" name="q" value="{{ $q ?? '' }}" placeholder="{{ trans('faq::messages.search_placeholder') }}">
        </div>
        @if(($deleted ?? false))
            <input type="hidden" name="deleted" value="1">
        @endif
        <button class="btn gray" type="submit">{{ trans('faq::messages.search_action') }}</button>
    </form>

    <p class="muted">
        @if($deleted ?? false)
            <a href="/admin/faqs">← {{ trans('faq::messages.live_tab') }}</a>
        @else
            <a href="/admin/faqs?deleted=1">{{ trans('faq::messages.deleted_tab') }} →</a>
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ trans('faq::messages.question_col') }}</th>
                <th>{{ trans('faq::messages.category_col') }}</th>
                <th>{{ trans('faq::messages.status_col') }}</th>
                <th>{{ trans('faq::messages.helpful_col') }}</th>
                <th>{{ trans('faq::messages.date_col') }}</th>
                <th>{{ trans('faq::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td>
                    <a href="/admin/faqs/{{ $item->id() }}/edit">{{ $item->question() }}</a>
                    @if($item->isVisible())
                        <a class="muted" href="/faq#q-{{ $item->id() }}" target="_blank" title="{{ trans('faq::messages.show_faq') }}">↗</a>
                    @endif
                </td>
                <td>{{ $item->categoryId() !== null ? ($categoryTitles[$item->categoryId()] ?? trans('faq::messages.uncategorized')) : trans('faq::messages.uncategorized') }}</td>
                <td><span class="badge {{ $item->status()->value }}">{{ trans('faq::status.' . $item->status()->value) }}</span></td>
                <td>{{ trans_number($item->helpfulYes()) }} / {{ trans_number($item->helpfulNo()) }}</td>
                <td>{{ $item->createdAt() !== null ? jalali($item->createdAt(), 'Y/m/d') : '—' }}</td>
                <td class="row-actions">
                    <a class="btn gray sm" href="/admin/faqs/{{ $item->id() }}/edit">{{ trans('faq::messages.edit') }}</a>
                    @if(!$item->isTrashed() && $item->status()->value === 'draft')
                    <form class="inline" method="post" action="/admin/faqs/{{ $item->id() }}/publish">
                        @csrf
                        <button class="btn green sm" type="submit">{{ trans('faq::messages.publish_action') }}</button>
                    </form>
                    @endif
                    @if(!$item->isTrashed() && $item->status()->value === 'published')
                    <form class="inline" method="post" action="/admin/faqs/{{ $item->id() }}/archive">
                        @csrf
                        <button class="btn sm" type="submit">{{ trans('faq::messages.archive_action') }}</button>
                    </form>
                    @endif
                    @if(!$item->isTrashed() && $item->status()->value === 'archived')
                    <form class="inline" method="post" action="/admin/faqs/{{ $item->id() }}/draft">
                        @csrf
                        <button class="btn sm" type="submit">{{ trans('faq::messages.draft_action') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/faqs/{{ $item->id() }}">
                        @csrf
                        @method('DELETE')
                        @if($item->isTrashed())
                            <button class="btn red sm" type="submit" onclick="return confirm('{{ trans('faq::messages.confirm_delete_forever') }}')">{{ trans('faq::messages.delete_forever') }}</button>
                        @else
                            <button class="btn red sm" type="submit" onclick="return confirm('{{ trans('faq::messages.confirm_delete') }}')">{{ trans('faq::messages.delete') }}</button>
                        @endif
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('faq::messages.no_faqs') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $currentPage = (int) ($page ?? 1);
        $fqQuery = http_build_query(array_filter([
            'status' => $status ?? '',
            'category_id' => $categoryId ?? '',
            'q' => $q ?? '',
            'deleted' => ($deleted ?? false) ? '1' : '',
        ], static fn ($v): bool => $v !== ''));
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $currentPage)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/faqs?page={{ $p }}{{ $fqQuery !== '' ? '&' . $fqQuery : '' }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
