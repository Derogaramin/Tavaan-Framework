@extends('content::layouts.page')

@section('pageTitle'){{ $page->seo()->title !== '' ? $page->seo()->title : $page->title() }}@endsection

@section('metaTags')
@foreach($page->seo()->metaTags() as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach($page->seo()->openGraph() as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@endsection

@section('content')
<article class="pg-card">
    <h1>{{ $page->title() }}</h1>
    <div class="pg-meta">
        @if($page->publishedAt() !== null)
            <time datetime="{{ $page->publishedAt()->format('Y-m-d') }}">{{ jalali($page->publishedAt(), 'Y/m/d') }}</time>
        @endif
        <span>{{ trans('content::messages.views', ['count' => trans_number($page->viewCount())]) }}</span>
    </div>

    <div class="page-body">
        {!! $page->body() ?? '' !!}
    </div>
</article>

@php
    $commentOk = \Tavaan\Core\Facade\Facades\Session::pull('comment_ok');
    $commentErrors = \Tavaan\Core\Facade\Facades\Session::pull('comment_errors') ?? [];
    $commentOld = \Tavaan\Core\Facade\Facades\Session::pull('comment_old') ?? [];
@endphp

@if($canComment || !empty($thread))
<section class="pg-card" id="comments">
    <h2>{{ trans('content::messages.comments') }}</h2>

    @if(empty($thread))
        <p class="muted">{{ trans('content::messages.no_comments') }}</p>
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
                    $html .= '<div class="pg-meta"><strong>' . $name . '</strong><span class="comment-meta">' . $date . '</span></div>';
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
@endif

@if($canComment)
<section class="pg-card" id="comment-form">
    <h3>{{ trans('content::messages.comment_form_title') }}</h3>

    @if(is_string($commentOk) && $commentOk !== '')
        <div class="pg-flash ok">{{ $commentOk }}</div>
    @endif
    @if(!empty($commentErrors))
        <div class="pg-flash error">
            @foreach($commentErrors as $field => $messages)
                @foreach((array) $messages as $message)
                    <div>• {{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    @endif

    <form method="post" action="/comments/page/{{ $page->slug() }}" class="pg-form">
        @csrf
        <input type="hidden" name="parent_id" value="">
        @guest
        <div class="field">
            <label for="author_name">{{ trans('content::messages.your_name') }}</label>
            <input type="text" id="author_name" name="author_name" value="{{ is_array($commentOld) ? (string) ($commentOld['author_name'] ?? '') : '' }}" required>
        </div>
        <div class="field">
            <label for="author_email">{{ trans('content::messages.your_email') }}</label>
            <input type="email" id="author_email" name="author_email" value="{{ is_array($commentOld) ? (string) ($commentOld['author_email'] ?? '') : '' }}" required>
        </div>
        @endguest
        <div class="field">
            <label for="body">{{ trans('content::messages.comment_body') }}</label>
            <textarea id="body" name="body" required>{{ is_array($commentOld) ? (string) ($commentOld['body'] ?? '') : '' }}</textarea>
        </div>
        <button class="pg-btn" type="submit">{{ trans('content::messages.comment_submit') }}</button>
    </form>
</section>
@elseif(!$page->commentsOpen())
<section class="pg-card">
    <p class="muted">{{ trans('content::messages.comments_closed') }}</p>
</section>
@endif
@endsection
