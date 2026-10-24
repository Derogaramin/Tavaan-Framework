<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('news::messages.news_title'))</title>
    @yield('metaTags')
    <link rel="alternate" type="application/rss+xml" title="{{ trans('news::messages.feed_title') }}" href="/news/feed">
    @stack('head')
    @verbatim
    <style>
        /* پوستهٔ پایهٔ ماژول خبری — کیت theme کامل را override می‌کند (AC-APP-04).
           RTL با CSS logical properties (DOC-090 §7). */
        :root { --n-primary: #b91c1c; --n-text: #111827; --n-muted: #6b7280; --n-bg: #f9fafb; --n-card: #fff; --n-border: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--n-bg); color: var(--n-text); line-height: 1.9; }
        a { color: var(--n-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .news-header { background: #111827; color: #f9fafb; border-block-end: 3px solid var(--n-primary); padding-block: .9rem; }
        .news-container { max-width: 1080px; margin-inline: auto; padding-inline: 1rem; }
        .news-nav { display: flex; gap: 1.1rem; align-items: center; flex-wrap: wrap; }
        .news-nav a { color: #e5e7eb; }
        .news-nav .brand { font-weight: 800; font-size: 1.25rem; color: #fff; }
        .breaking-bar { background: #fef2f2; border-block-end: 1px solid #fecaca; padding-block: .5rem; }
        .breaking-bar .label { background: var(--n-primary); color: #fff; border-radius: 6px; padding-inline: .6rem; font-size: .8rem; font-weight: 700; }
        .breaking-bar ul { display: inline; margin: 0; padding-inline-start: .8rem; list-style: none; }
        .breaking-bar li { display: inline; }
        .breaking-bar li + li::before { content: " • "; color: var(--n-muted); }
        .news-main { padding-block: 1.5rem; }
        .news-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .news-layout { grid-template-columns: 2.2fr 1fr; } }
        .news-card { background: var(--n-card); border: 1px solid var(--n-border); border-radius: 10px; padding: 1.1rem 1.25rem; margin-block-end: .9rem; }
        .news-card h2, .news-card h3 { margin-block: .2rem .4rem; }
        .news-meta { color: var(--n-muted); font-size: .83rem; display: flex; gap: .8rem; flex-wrap: wrap; }
        .news-tag { background: #eef2ff; color: #3730a3; border-radius: 999px; padding-inline: .6rem; padding-block: .1rem; font-size: .76rem; }
        .badge-breaking { background: var(--n-primary); color: #fff; border-radius: 6px; padding-inline: .5rem; font-size: .72rem; font-weight: 700; }
        .lead { color: #374151; }
        .news-footer { background: #111827; color: #9ca3af; padding-block: 1.4rem; margin-block-start: 2.5rem; text-align: center; font-size: .85rem; }
        .news-footer a { color: #f3f4f6; }
        .news-pagination { display: flex; gap: .5rem; justify-content: center; margin-block: 1.5rem; flex-wrap: wrap; }
        .news-pagination a, .news-pagination span { border: 1px solid var(--n-border); border-radius: 8px; padding-inline: .7rem; padding-block: .2rem; background: var(--n-card); }
        .news-pagination .current { background: var(--n-primary); color: #fff; border-color: var(--n-primary); }
        .news-form label { display: block; margin-block-end: .25rem; font-weight: 600; font-size: .9rem; }
        .news-form input[type=text], .news-form input[type=email], .news-form textarea, .news-form select {
            width: 100%; padding: .55rem .7rem; border: 1px solid var(--n-border); border-radius: 8px; font-family: inherit; font-size: .95rem; background: #fff; color: var(--n-text);
        }
        .news-form textarea { min-height: 7rem; }
        .news-form .field { margin-block-end: .9rem; }
        .news-btn { display: inline-block; background: var(--n-primary); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .92rem; cursor: pointer; }
        .news-btn:hover { filter: brightness(1.12); text-decoration: none; }
        .news-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .news-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .news-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .article-body { font-size: 1.03rem; }
        .article-body pre { background: #111827; color: #e5e7eb; padding: 1rem; border-radius: 8px; overflow-x: auto; direction: ltr; }
        .article-body code { background: #f3f4f6; border-radius: 4px; padding-inline: .3rem; }
        .article-body pre code { background: none; padding: 0; }
        .article-body blockquote { border-inline-start: 3px solid var(--n-primary); margin-inline-start: 0; padding-inline-start: 1rem; color: var(--n-muted); }
        .article-body img, .gallery img { max-width: 100%; height: auto; border-radius: 8px; }
        .gallery { display: flex; gap: .6rem; flex-wrap: wrap; margin-block: 1rem; }
        .gallery img { max-width: 220px; }
        .related a { display: block; margin-block-end: .4rem; }
        .comment { border-block-end: 1px solid var(--n-border); padding-block: .8rem; }
        .comment:last-child { border-block-end: 0; }
        .comment-children { margin-inline-start: 1.5rem; border-inline-start: 2px solid var(--n-border); padding-inline-start: 1rem; }
        .comment-meta { color: var(--n-muted); font-size: .8rem; }
        .muted { color: var(--n-muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: .5rem .6rem; border-block-end: 1px solid var(--n-border); font-size: .9rem; }
        th { color: var(--n-muted); }
    </style>
    @endverbatim
</head>
<body>
    <header class="news-header">
        <div class="news-container news-nav">
            <a class="brand" href="/news">📰 {{ trans('news::messages.news_title') }}</a>
            <a href="/news">{{ trans('news::messages.nav_home') }}</a>
            <a href="/news/feed">{{ trans('news::messages.nav_feed') }}</a>
            <a href="/news/sitemap.xml">sitemap</a>
        </div>
    </header>

    @if(!empty($breaking ?? []))
    <div class="breaking-bar">
        <div class="news-container">
            <span class="label">{{ trans('news::messages.breaking') }}</span>
            <ul>
                @foreach($breaking as $breakingItem)
                    <li><a href="/news/{{ $breakingItem->slug() }}">{{ $breakingItem->title() }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <main class="news-main">
        <div class="news-container">
            @yield('content')
        </div>
    </main>

    <footer class="news-footer">
        <div class="news-container">
            {{ trans('news::messages.powered_by') }} — {{ jalali(null, 'Y/m/d') }}
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
