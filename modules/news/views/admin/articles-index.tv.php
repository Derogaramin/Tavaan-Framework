@extends('news::admin.layout')

@section('content')
@php $ok = \Tavaan\Core\Facade\Facades\Session::pull('news_article_ok'); @endphp
@if(is_string($ok) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

<div class="card">
    <h2>{{ trans('news::messages.admin_articles') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/news/articles" style="margin-block-end:1rem">
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ trans('news::messages.all_statuses') }}</option>
            @foreach(['draft', 'review', 'approved', 'published', 'archived'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ trans('news::status.' . $s) }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('news::messages.title_col') }}</th>
                <th>{{ trans('news::messages.status_col') }}</th>
                @if($isEditor)<th>{{ trans('news::messages.author_col') }}</th>@endif
                <th>{{ trans('news::messages.date_col') }}</th>
                <th>{{ trans('news::messages.views_col') }}</th>
                <th>{{ trans('news::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($articles as $article)
            <tr>
                <td>
                    <a href="/admin/news/articles/{{ $article->id() }}/edit">{{ $article->title() }}</a>
                    @if($article->isBreaking())<span class="badge archived">{{ trans('news::messages.breaking') }}</span>@endif
                    @if($article->isPublished())
                        <a class="muted" href="/news/{{ $article->slug() }}" target="_blank" title="نمایش">↗</a>
                    @endif
                </td>
                <td><span class="badge {{ $article->status()->value }}">{{ trans('news::status.' . $article->status()->value) }}</span></td>
                @if($isEditor)
                    <td>{{ ($people[(string) $article->authorId()] ?? null)?->displayName() ?? '—' }}</td>
                @endif
                <td>{{ $article->publishedAt() !== null ? jalali($article->publishedAt(), 'Y/m/d') : ($article->createdAt() !== null ? jalali($article->createdAt(), 'Y/m/d') : '—') }}</td>
                <td>{{ trans_number($article->viewCount()) }}</td>
                <td class="row-actions">
                    <a class="btn gray" href="/admin/news/articles/{{ $article->id() }}/edit">{{ trans('news::messages.edit') }}</a>
                    <form class="inline" method="post" action="/admin/news/articles/{{ $article->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('news::messages.confirm_delete') }}')">{{ trans('news::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('news::messages.no_news') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php $lastPage = (int) max(1, ceil($total / max(1, $perPage))); @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/news/articles?page={{ $p }}&status={{ $status }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
