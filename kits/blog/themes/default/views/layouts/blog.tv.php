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
        /* پوستهٔ پیش‌فرض کیت وبلاگ (kits/blog/themes/default) — RTL کامل با
           CSS logical properties (DOC-090 §7). Override ماژول: AC-APP-04. */
        :root {
            --kit-primary: #0ea5e9; --kit-primary-dark: #0369a1; --kit-accent: #f59e0b;
            --kit-text: #0f172a; --kit-muted: #64748b; --kit-bg: #f0f9ff;
            --kit-card: #ffffff; --kit-border: #e0f2fe; --kit-radius: 14px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; color: var(--kit-text); line-height: 1.9;
            background: radial-gradient(1200px 400px at 50% -100px, #e0f2fe 0%, transparent 100%), var(--kit-bg);
        }
        a { color: var(--kit-primary-dark); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .blog-header { background: linear-gradient(180deg, #0c4a6e 0%, #075985 100%); border-block-end: 3px solid var(--kit-accent); padding-block: 1.1rem; }
        .blog-container { max-width: 980px; margin-inline: auto; padding-inline: 1rem; }
        .blog-nav { display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap; }
        .blog-nav a { color: #e0f2fe; }
        .blog-nav .brand { font-weight: 800; font-size: 1.3rem; color: #fff; }
        .blog-main { padding-block: 1.75rem; }
        .blog-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .blog-layout { grid-template-columns: 2fr 1fr; } }
        .blog-card {
            background: var(--kit-card); border: 1px solid var(--kit-border); border-radius: var(--kit-radius);
            padding: 1.4rem; margin-block-end: 1.1rem; box-shadow: 0 1px 3px rgb(14 165 233 / 8%);
        }
        .blog-card h2 a { color: var(--kit-primary-dark); }
        .blog-meta { color: var(--kit-muted); font-size: .85rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .blog-tag { background: #fef3c7; color: #92400e; border-radius: 999px; padding-inline: .65rem; padding-block: .12rem; font-size: .78rem; }
        .blog-sidebar .blog-card { padding: 1.1rem; }
        .blog-footer { background: #0c4a6e; color: #bae6fd; border-block-start: 3px solid var(--kit-accent); padding-block: 1.6rem; margin-block-start: 2.5rem; text-align: center; }
        .blog-footer a { color: #fff; }
        .blog-pagination { display: flex; gap: .5rem; justify-content: center; margin-block: 1.5rem; flex-wrap: wrap; }
        .blog-pagination a, .blog-pagination span { border: 1px solid var(--kit-border); border-radius: 8px; padding-inline: .75rem; padding-block: .25rem; background: var(--kit-card); }
        .blog-pagination .current { background: var(--kit-primary-dark); color: #fff; border-color: var(--kit-primary-dark); }
        .blog-form label { display: block; margin-block-end: .25rem; font-weight: 600; font-size: .9rem; }
        .blog-form input[type=text], .blog-form input[type=email], .blog-form textarea, .blog-form select {
            width: 100%; padding: .6rem .75rem; border: 1px solid var(--kit-border); border-radius: 10px;
            font-family: inherit; font-size: .95rem; background: #fff; color: var(--kit-text);
        }
        .blog-form textarea { min-height: 7rem; }
        .blog-form .field { margin-block-end: .9rem; }
        .blog-btn { display: inline-block; background: var(--kit-primary-dark); color: #fff; border: 0; border-radius: 10px; padding: .6rem 1.3rem; font-family: inherit; font-size: .95rem; cursor: pointer; }
        .blog-btn:hover { background: var(--kit-primary); text-decoration: none; }
        .blog-flash { border-radius: 10px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .blog-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .blog-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .post-body { font-size: 1.04rem; }
        .post-body pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: 10px; overflow-x: auto; direction: ltr; }
        .post-body code { background: #f1f5f9; border-radius: 4px; padding-inline: .3rem; }
        .post-body pre code { background: none; padding: 0; }
        .post-body blockquote { border-inline-start: 3px solid var(--kit-accent); margin-inline-start: 0; padding-inline-start: 1rem; color: var(--kit-muted); }
        .post-body img { max-width: 100%; height: auto; border-radius: 10px; }
        .comment { border-block-end: 1px solid var(--kit-border); padding-block: .85rem; }
        .comment:last-child { border-block-end: 0; }
        .comment-children { margin-inline-start: 1.5rem; border-inline-start: 2px solid var(--kit-border); padding-inline-start: 1rem; }
        .comment-meta { color: var(--kit-muted); font-size: .8rem; }
        .muted { color: var(--kit-muted); }
    </style>
    @endverbatim
</head>
<body>
    <header class="blog-header">
        <div class="blog-container blog-nav">
            <a class="brand" href="/blog">⚡ {{ trans('blog::messages.blog_title') }}</a>
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
            {{ trans('blog::messages.powered_by') }} — {{ jalali(null, 'Y/m/d') }}
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
