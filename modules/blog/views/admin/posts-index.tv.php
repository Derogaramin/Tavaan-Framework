@extends('blog::admin.layout')

@section('content')
@php $ok = \Tavaan\Core\Facade\Facades\Session::pull('blog_post_ok'); @endphp
@if(is_string($ok) && $ok !== '')
    <div class="flash-ok">{{ $ok }}</div>
@endif

<div class="card">
    <h2>{{ trans('blog::messages.admin_posts') }} <span class="muted">({{ trans_number($total) }})</span></h2>

    <form method="get" action="/admin/blog/posts" style="margin-block-end:1rem">
        <select name="status" onchange="this.form.submit()">
            <option value="">{{ trans('blog::messages.all_statuses') }}</option>
            @foreach(['draft', 'published', 'scheduled', 'archived'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ trans('content::status.' . $s) }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr>
                <th>{{ trans('blog::messages.title_col') }}</th>
                <th>{{ trans('blog::messages.status_col') }}</th>
                @if($isEditor)<th>{{ trans('blog::messages.author_col') }}</th>@endif
                <th>{{ trans('blog::messages.date_col') }}</th>
                <th>{{ trans('blog::messages.views_col') }}</th>
                <th>{{ trans('blog::messages.actions_col') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($posts as $post)
            <tr>
                <td>
                    <a href="/admin/blog/posts/{{ $post->id() }}/edit">{{ $post->title() }}</a>
                    @if($post->isPublished())
                        <a class="muted" href="/blog/{{ $post->slug() }}" target="_blank" title="نمایش">↗</a>
                    @endif
                </td>
                <td><span class="badge {{ $post->status()->value }}">{{ trans('content::status.' . $post->status()->value) }}</span></td>
                @if($isEditor)
                    <td>{{ ($authors[(string) $post->authorId()] ?? null)?->displayName() ?? '—' }}</td>
                @endif
                <td>{{ $post->publishedAt() !== null ? jalali($post->publishedAt(), 'Y/m/d') : ($post->createdAt() !== null ? jalali($post->createdAt(), 'Y/m/d') : '—') }}</td>
                <td>{{ trans_number($post->viewCount()) }}</td>
                <td class="row-actions">
                    <a class="btn gray" href="/admin/blog/posts/{{ $post->id() }}/edit">{{ trans('blog::messages.edit') }}</a>
                    @if(!$post->status()->isPublic())
                    <form class="inline" method="post" action="/admin/blog/posts/{{ $post->id() }}/publish">
                        @csrf
                        <button class="btn green" type="submit">{{ trans('blog::messages.publish') }}</button>
                    </form>
                    @endif
                    <form class="inline" method="post" action="/admin/blog/posts/{{ $post->id() }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn red" type="submit" onclick="return confirm('{{ trans('blog::messages.confirm_delete') }}')">{{ trans('blog::messages.delete') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ trans('blog::messages.no_posts') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    @php
        $lastPage = (int) max(1, ceil($total / max(1, $perPage)));
    @endphp
    @if($lastPage > 1)
    <nav class="pagination">
        @for($p = 1; $p <= $lastPage; $p++)
            @if($p === $page)
                <span class="current">{{ trans_number($p) }}</span>
            @else
                <a href="/admin/blog/posts?page={{ $p }}&status={{ $status }}">{{ trans_number($p) }}</a>
            @endif
        @endfor
    </nav>
    @endif
</div>
@endsection
