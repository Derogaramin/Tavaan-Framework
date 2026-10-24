@extends('blog::layouts.blog')

@section('pageTitle'){{ $post->seo()->title !== '' ? $post->seo()->title : $post->title() }}@endsection

@section('metaTags')
@foreach($post->seo()->metaTags() as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach($post->seo()->openGraph() as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@endsection

@section('content')
<article class="blog-card">
    <h1>{{ $post->title() }}</h1>
    <div class="blog-meta">
        @if($post->publishedAt() !== null)
            <time datetime="{{ $post->publishedAt()->format('Y-m-d') }}">{{ jalali($post->publishedAt(), 'Y/m/d') }}</time>
        @endif
        @if(isset($author) && $author !== null)
            <span>{{ trans('blog::messages.by_author', ['name' => $author->displayName()]) }}</span>
        @endif
        <span>{{ trans('blog::messages.views', ['count' => trans_number($post->viewCount())]) }}</span>
    </div>

    <div class="post-body">
        {!! $post->body() ?? '' !!}
    </div>

    @if(!empty($tags))
        <div class="blog-meta" style="margin-block-start:1rem">
            @foreach($tags as $tag)
                <a class="blog-tag" href="/blog/tag/{{ $tag->slug() }}">#{{ $tag->name() }}</a>
            @endforeach
        </div>
    @endif
</article>

<section class="blog-card" id="comments">
    <h2>{{ trans('blog::messages.comments') }}</h2>

    @if(empty($thread))
        <p class="muted">{{ trans('blog::messages.no_comments') }}</p>
    @else
        @php
            $renderNodes = function (array $nodes) use (&$renderNodes): string {
                $html = '';
                foreach ($nodes as $node) {
                    $c = $node->comment;
                    $name = htmlspecialchars($c->authorName, ENT_QUOTES, 'UTF-8');
                    $body = nl2br(htmlspecialchars($c->body, ENT_QUOTES, 'UTF-8'));
                    $date = $c->createdAt !== null ? jalali($c->createdAt, 'Y/m/d H:i') : '';
                    $html .= '<div class="comment">';
                    $html .= '<div class="blog-meta"><strong>' . $name . '</strong><span class="comment-meta">' . $date . '</span></div>';
                    $html .= '<div>' . $body . '</div>';
                    if ($node->children !== []) {
                        $html .= '<div class="comment-children">' . $renderNodes($node->children) . '</div>';
                    }
                    $html .= '</div>';
                }
                return $html;
            };
        @endphp
        {!! $renderNodes($thread) !!}
    @endif
</section>

@if($commentsEnabled)
<section class="blog-card" id="comment-form">
    <h3>{{ trans('blog::messages.comment_form_title') }}</h3>

    @if(is_array($flash ?? null))
        @if(($flash['status'] ?? '') === 'ok')
            <div class="blog-flash ok">{{ $flash['message'] ?? '' }}</div>
        @elseif(($flash['status'] ?? '') === 'error')
            <div class="blog-flash error">
                @foreach(($flash['errors'] ?? []) as $field => $messages)
                    @foreach($messages as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                @endforeach
            </div>
        @endif
    @endif

    <form method="post" action="/blog/{{ $post->slug() }}/comments" class="blog-form">
        @csrf
        <input type="hidden" name="parent_id" value="">
        @guest
        <div class="field">
            <label for="author_name">{{ trans('blog::messages.your_name') }}</label>
            <input type="text" id="author_name" name="author_name" value="{{ is_array($flash ?? null) ? ($flash['old']['author_name'] ?? '') : '' }}" required>
        </div>
        <div class="field">
            <label for="author_email">{{ trans('blog::messages.your_email') }}</label>
            <input type="email" id="author_email" name="author_email" required>
        </div>
        @endguest
        <div class="field">
            <label for="body">{{ trans('blog::messages.comment_body') }}</label>
            <textarea id="body" name="body" required>{{ is_array($flash ?? null) ? ($flash['old']['body'] ?? '') : '' }}</textarea>
        </div>
        <button class="blog-btn" type="submit">{{ trans('blog::messages.comment_submit') }}</button>
    </form>
</section>
@else
<section class="blog-card">
    <p class="muted">{{ trans('comment::messages.closed') }}</p>
</section>
@endif
@endsection
