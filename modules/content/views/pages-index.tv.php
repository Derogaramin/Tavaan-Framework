@extends('content::layouts.page')

@section('pageTitle'){{ trans('content::messages.pages_title') }}@endsection

@section('content')
<h1>{{ trans('content::messages.pages_title') }}</h1>

@forelse($pages as $page)
    <article class="pg-card page-item">
        <h2><a href="/pages/{{ $page->slug() }}">{{ $page->title() }}</a></h2>
        @if($page->excerpt() !== null && $page->excerpt() !== '')
            <p>{{ $page->excerpt() }}</p>
        @endif
        <div class="pg-meta">
            @if($page->publishedAt() !== null)
                <time datetime="{{ $page->publishedAt()->format('Y-m-d') }}">{{ jalali($page->publishedAt(), 'Y/m/d') }}</time>
            @endif
            <span>{{ trans('content::messages.views', ['count' => trans_number($page->viewCount())]) }}</span>
        </div>
    </article>
@empty
    <div class="pg-card">
        <p class="muted">{{ trans('content::messages.no_pages') }}</p>
    </div>
@endforelse
@endsection
