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
        <span>{{ trans('news::messages.views', ['count' => trans_number($article->viewCount())]) }}</span>
    </div>
    <h2><a href="/news/{{ $article->slug() }}">{{ $article->title() }}</a></h2>
    @if($article->lead() !== null && $article->lead() !== '')
        <p class="lead">{{ $article->lead() }}</p>
    @endif
    @if(!empty($articleTags))
        <div class="news-meta">
            @foreach($articleTags as $tag)
                <a class="news-tag" href="/news/tag/{{ $tag->slug() }}">#{{ $tag->name() }}</a>
            @endforeach
        </div>
    @endif
</article>
