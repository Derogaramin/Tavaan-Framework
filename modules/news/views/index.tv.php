@extends('news::layouts.news')

@section('content')
<div class="news-layout">
    <div>
        <h1>{{ $heading }}</h1>
        @if(!empty($headingDescription))
            <p class="muted">{{ $headingDescription }}</p>
        @endif

        @forelse($result['rows'] as $article)
            @include('news::partials.article-card', [
                'article' => $article,
                'author' => $result['authors'][(string) $article->authorId()] ?? null,
                'articleTags' => $result['tags'][$article->id()] ?? [],
            ])
        @empty
            <div class="news-card">
                <p class="muted">{{ trans('news::messages.no_news') }}</p>
            </div>
        @endforelse

        @include('news::partials.pagination', [
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
            'baseUrl' => '/news',
        ])
    </div>

    <aside class="blog-sidebar">
        <div class="news-card">
            <form method="get" action="/news" class="news-form">
                <div class="field">
                    <label for="q">{{ trans('news::messages.search') }}</label>
                    <input type="text" id="q" name="q" value="{{ $query }}" placeholder="{{ trans('news::messages.search_placeholder') }}">
                </div>
                <button class="news-btn" type="submit">{{ trans('news::messages.search_submit') }}</button>
            </form>
        </div>

        <div class="news-card">
            <strong>{{ trans('news::messages.categories') }}</strong>
            <ul>
                @forelse($categories as $category)
                    <li>
                        <a href="/news/category/{{ $category->slug() }}">{{ $category->title() }}</a>
                        @if($category->parentId() !== null)<span class="muted">({{ trans('news::messages.sub_category') }})</span>@endif
                    </li>
                @empty
                    <li class="muted">{{ trans('news::messages.no_categories') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="news-card">
            <strong>{{ trans('news::messages.archive') }}</strong>
            <p class="muted"><a href="/news/archive/{{ jalali(null, 'Y/m') }}">{{ trans('news::messages.this_month') }}</a></p>
        </div>
    </aside>
</div>
@endsection
