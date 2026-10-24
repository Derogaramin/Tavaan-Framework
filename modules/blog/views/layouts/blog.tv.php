<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('blog::messages.blog_title'))</title>
    @yield('metaTags')
    <link rel="alternate" type="application/rss+xml" title="{{ trans('blog::messages.feed_title') }}" href="/blog/feed">
    @stack('head')
    @verbatim
    <style>
        /* پوستهٔ پایهٔ ماژول — کیت theme کامل را override می‌کند (AC-APP-04).
           RTL با CSS logical properties (DOC-090 §7). */
        :root { --blog-primary: #2563eb; --blog-text: #0f172a; --blog-muted: #64748b; --blog-bg: #f8fafc; --blog-card: #ffffff; --blog-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--blog-bg); color: var(--blog-text); line-height: 1.9; }
        a { color: var(--blog-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .blog-header { background: var(--blog-card); border-block-end: 1px solid var(--blog-border); padding-block: 1rem; }
        .blog-container { max-width: 960px; margin-inline: auto; padding-inline: 1rem; }
        .blog-nav { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .blog-nav .brand { font-weight: 700; font-size: 1.15rem; color: var(--blog-text); }
        .blog-main { padding-block: 1.5rem; }
        .blog-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .blog-layout { grid-template-columns: 2fr 1fr; } }
        .blog-card { background: var(--blog-card); border: 1px solid var(--blog-border); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .blog-meta { color: var(--blog-muted); font-size: .85rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .blog-tag { background: #eff6ff; color: var(--blog-primary); border-radius: 999px; padding-inline: .6rem; padding-block: .1rem; font-size: .78rem; }
        .blog-sidebar .blog-card { padding: 1rem; }
        .blog-footer { border-block-start: 1px solid var(--blog-border); color: var(--blog-muted); font-size: .85rem; padding-block: 1.5rem; margin-block-start: 2rem; text-align: center; }
        .blog-pagination { display: flex; gap: .5rem; justify-content: center; margin-block: 1.5rem; flex-wrap: wrap; }
        .blog-pagination a, .blog-pagination span { border: 1px solid var(--blog-border); border-radius: 8px; padding-inline: .7rem; padding-block: .25rem; background: var(--blog-card); }
        .blog-pagination .current { background: var(--blog-primary); color: #fff; border-color: var(--blog-primary); }
        .blog-form label { display: block; margin-block-end: .25rem; font-weight: 600; font-size: .9rem; }
        .blog-form input[type=text], .blog-form input[type=email], .blog-form textarea, .blog-form select {
            width: 100%; padding: .55rem .7rem; border: 1px solid var(--blog-border); border-radius: 8px;
            font-family: inherit; font-size: .95rem; background: #fff; color: var(--blog-text);
        }
        .blog-form textarea { min-height: 7rem; }
        .blog-form .field { margin-block-end: .9rem; }
        .blog-btn { display: inline-block; background: var(--blog-primary); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .95rem; cursor: pointer; }
        .blog-btn:hover { filter: brightness(1.1); text-decoration: none; }
        .blog-btn.secondary { background: #475569; }
        .blog-btn.danger { background: #dc2626; }
        .blog-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .blog-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .blog-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .post-body { font-size: 1.02rem; }
        .post-body pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: 8px; overflow-x: auto; direction: ltr; }
        .post-body code { background: #f1f5f9; border-radius: 4px; padding-inline: .3rem; }
        .post-body pre code { background: none; padding: 0; }
        .post-body blockquote { border-inline-start: 3px solid var(--blog-primary); margin-inline-start: 0; padding-inline-start: 1rem; color: var(--blog-muted); }
        .post-body img { max-width: 100%; height: auto; border-radius: 8px; }
        .comment { border-block-end: 1px solid var(--blog-border); padding-block: .8rem; }
        .comment:last-child { border-block-end: 0; }
        .comment-children { margin-inline-start: 1.5rem; border-inline-start: 2px solid var(--blog-border); padding-inline-start: 1rem; }
        .comment-meta { color: var(--blog-muted); font-size: .8rem; }
        .blog-table { width: 100%; border-collapse: collapse; }
        .blog-table th, .blog-table td { text-align: start; padding: .5rem .6rem; border-block-end: 1px solid var(--blog-border); font-size: .9rem; }
        .blog-table th { color: var(--blog-muted); font-weight: 600; }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .75rem; }
        .badge.published { background: #ecfdf5; color: #065f46; }
        .badge.draft { background: #f1f5f9; color: #475569; }
        .badge.scheduled { background: #fffbeb; color: #92400e; }
        .badge.archived { background: #fef2f2; color: #991b1b; }
        .badge.pending { background: #fffbeb; color: #92400e; }
        .badge.approved { background: #ecfdf5; color: #065f46; }
        .badge.spam { background: #fef2f2; color: #991b1b; }
        .inline-form { display: inline; }
        .muted { color: var(--blog-muted); }
        .row-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    </style>
    @endverbatim
</head>
<body>
    <header class="blog-header">
        <div class="blog-container blog-nav">
            <a class="brand" href="/blog">{{ trans('blog::messages.blog_title') }}</a>
            <a href="/blog">{{ trans('blog::messages.nav_home') }}</a>
            <a href="/blog/feed">{{ trans('blog::messages.nav_feed') }}</a>
            <a href="/blog/sitemap.xml">sitemap</a>
        </div>
    </header>

    <main class="blog-main">
        <div class="blog-container">
            @yield('content')
        </div>
    </main>

    <footer class="blog-footer">
        <div class="blog-container">
            {{ trans('blog::messages.powered_by') }}
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
