@extends('content::admin.layout')

@section('content')
@if(is_string($ok ?? null) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

<div class="card">
    <h2>{{ trans('content::messages.admin_pages') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/pages" style="margin-block-end:1rem">
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ trans('content::messages.all_statuses') }}</option>
            @foreach(['draft', 'published', 'archived'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ trans('content::status.' . $s) }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('content::messages.title_col') }}</th>
                <th>{{ trans('content::messages.status_col') }}</th>
                <th>{{ trans('content::messages.date_col') }}</th>
                <th>{{ trans('content::messages.views_col') }}</th>
                <th>{{ trans('content::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pages as $item)
            <tr>
                <td>
                    <a href="/admin/pages/{{ $item->id() }}/edit">{{ $item->title() }}</a>
                    @if($item->isPublished())
                        <a class="muted" href="/pages/{{ $item->slug() }}" target="_blank" title="{{ trans('content::messages.show_page') }}">↗</a>
                    @endif
                </td>
                <td><span class="badge {{ $item->status()->value }}">{{ trans('content::status.' . $item->status()->value) }}</span></td>
                <td>{{ $item->publishedAt() !== null ? jalali($item->publishedAt(), 'Y/m/d') : ($item->createdAt() !== null ? jalali($item->createdAt(), 'Y/m/d') : '—') }}</td>
                <td>{{ trans_number($item->viewCount()) }}</td>
                <td class="row-actions">
                    <a class="btn gray" href="/admin/pages/{{ $item->id() }}/edit">{{ trans('content::messages.edit') }}</a>
                    @if(!$item->status()->isPublic())
                    <form class="inline" method="post" action="/admin/pages/{{ $item->id() }}/publish">
                        @csrf
                        <button class="btn green" type="submit">{{ trans('content::messages.publish') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/pages/{{ $item->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('content::messages.confirm_delete') }}')">{{ trans('content::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ trans('content::messages.no_pages') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
        $currentPage = (int) ($page ?? 1);
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $currentPage)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/pages?page={{ $p }}&status={{ $status }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
