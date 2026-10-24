<article class="blog-card">
    <h2><a href="/blog/{{ $post->slug() }}">{{ $post->title() }}</a></h2>
    <div class="blog-meta">
        @if($post->publishedAt() !== null)
            <time datetime="{{ $post->publishedAt()->format('Y-m-d') }}">{{ jalali($post->publishedAt(), 'Y/m/d') }}</time>
        @endif
        @if(isset($author) && $author !== null)
            <span>{{ trans('blog::messages.by_author', ['name' => $author->displayName()]) }}</span>
        @endif
        <span>{{ trans('blog::messages.views', ['count' => trans_number($post->viewCount())]) }}</span>
    </div>
    @if($post->excerpt() !== null && $post->excerpt() !== '')
        <p>{{ $post->excerpt() }}</p>
    @endif
    @if(!empty($postTags))
        <div class="blog-meta">
            @foreach($postTags as $tag)
                <a class="blog-tag" href="/blog/tag/{{ $tag->slug() }}">#{{ $tag->name() }}</a>
            @endforeach
        </div>
    @endif
</article>
