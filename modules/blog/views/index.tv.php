@extends('blog::layouts.blog')

@section('content')
<div class="blog-layout">
    <div>
        <h1>{{ $heading }}</h1>
        @if(!empty($headingDescription))
            <p class="muted">{{ $headingDescription }}</p>
        @endif

        @if($query !== '')
        <p class="muted">{{ trans('blog::messages.search_count', ['count' => $result['total']]) }}</p>
        @endif

        @forelse($result['rows'] as $post)
            @include('blog::partials.post-card', ['post' => $post, 'author' => $result['authors'][(string) $post->authorId()] ?? null, 'postTags' => $result['tags'][$post->id()] ?? []])
        @empty
            <div class="blog-card">
                <p class="muted">{{ trans('blog::messages.no_posts') }}</p>
            </div>
        @endforelse

        @include('blog::partials.pagination', [
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
        ])
    </div>

    <aside class="blog-sidebar">
        <div class="blog-card">
            <form method="get" action="/blog" class="blog-form">
                <div class="field">
                    <label for="q">{{ trans('blog::messages.search') }}</label>
                    <input type="text" id="q" name="q" value="{{ $query }}" placeholder="{{ trans('blog::messages.search_placeholder') }}">
                </div>
                <button class="blog-btn" type="submit">{{ trans('blog::messages.search_submit') }}</button>
            </form>
        </div>

        <div class="blog-card">
            <strong>{{ trans('blog::messages.categories') }}</strong>
            <ul>
                @forelse($categories as $category)
                    <li><a href="/blog/category/{{ $category->slug() }}">{{ $category->name() }}</a></li>
                @empty
                    <li class="muted">{{ trans('blog::messages.no_categories') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="blog-card">
            <strong>{{ trans('blog::messages.archive') }}</strong>
            <p class="muted">{{ jalali(null, 'Y/m') }} — <a href="/blog/archive/{{ jalali(null, 'Y/m') }}">{{ trans('blog::messages.this_month') }}</a></p>
        </div>
    </aside>
</div>
@endsection
