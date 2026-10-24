@extends('news::layouts.news')

@section('pageTitle'){{ $article->seo()->title !== '' ? $article->seo()->title : $article->title() }}@endsection

@section('metaTags')
@foreach($article->seo()->metaTags() as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach($article->seo()->openGraph() as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@if($featuredImageUrl !== null)
<meta property="og:image" content="{{ $featuredImageUrl }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<link rel="alternate" type="application/rss+xml" href="/news/feed">
<script type="application/ld+json">@json($jsonLd)</script>
@endsection

@section('content')
<article class="news-card">
    <div class="news-meta">
        @if($article->isBreaking())
            <span class="badge-breaking">{{ trans('news::messages.breaking') }}</span>
        @endif
        @if($article->publishedAt() !== null)
            <time datetime="{{ $article->publishedAt()->format('Y-m-d\TH:i:sP') }}">{{ jalali($article->publishedAt(), 'Y/m/d H:i') }}</time>
        @endif
        @if(isset($author) && $author !== null)
            <span>{{ trans('news::messages.by_author', ['name' => $author->displayName()]) }}</span>
        @endif
        @if(isset($editor) && $editor !== null)
            <span>{{ trans('news::messages.edited_by', ['name' => $editor->displayName()]) }}</span>
        @endif
        <span>{{ trans('news::messages.views', ['count' => trans_number($article->viewCount())]) }}</span>
        <a href="/news/{{ $article->slug() }}/amp" rel="amphtml">AMP</a>
    </div>

    <h1>{{ $article->title() }}</h1>

    @if($article->lead() !== null && $article->lead() !== '')
        <p class="lead"><strong>{{ $article->lead() }}</strong></p>
    @endif

    @if($featuredImageUrl !== null)
        <img src="{{ $featuredImageUrl }}" alt="{{ $article->title() }}" style="width:100%">
    @endif

    <div class="article-body">
        {!! $article->body() ?? '' !!}
    </div>

    @if(!empty($galleryUrls))
    <div class="gallery">
        @foreach($galleryUrls as $galleryUrl)
            <img src="{{ $galleryUrl }}" alt="{{ $article->title() }}" loading="lazy">
        @endforeach
    </div>
    @endif

    @if(!empty($tags))
        <div class="news-meta" style="margin-block-start:1rem">
            @foreach($tags as $tag)
                <a class="news-tag" href="/news/tag/{{ $tag->slug() }}">#{{ $tag->name() }}</a>
            @endforeach
        </div>
    @endif
</article>

@if(!empty($related))
<section class="news-card related">
    <h3>{{ trans('news::messages.related_news') }}</h3>
    @foreach($related as $relatedItem)
        <a href="/news/{{ $relatedItem->slug() }}">{{ $relatedItem->title() }}</a>
    @endforeach
</section>
@endif

@include('news::partials.comments', [
    'thread' => $thread,
    'commentsEnabled' => $commentsEnabled,
    'flash' => $flash ?? null,
    'article' => $article,
])
@endsection
