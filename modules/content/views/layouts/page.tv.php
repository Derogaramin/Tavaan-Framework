<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('content::messages.pages_title'))</title>
    @yield('metaTags')
    @verbatim
    <style>
        /* پوستهٔ پایهٔ صفحات ماژول محتوا — RTL با CSS logical properties (DOC-090 §7). */
        :root { --pg-primary: #0d9488; --pg-text: #0f172a; --pg-muted: #64748b; --pg-bg: #f8fafc; --pg-card: #ffffff; --pg-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--pg-bg); color: var(--pg-text); line-height: 1.9; }
        a { color: var(--pg-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .pg-header { background: var(--pg-card); border-block-end: 1px solid var(--pg-border); padding-block: 1rem; }
        .pg-container { max-width: 960px; margin-inline: auto; padding-inline: 1rem; }
        .pg-nav { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .pg-nav .brand { font-weight: 700; font-size: 1.15rem; color: var(--pg-text); }
        .pg-main { padding-block: 1.5rem; }
        .pg-card { background: var(--pg-card); border: 1px solid var(--pg-border); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .pg-meta { color: var(--pg-muted); font-size: .85rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .pg-footer { border-block-start: 1px solid var(--pg-border); color: var(--pg-muted); font-size: .85rem; padding-block: 1.5rem; margin-block-start: 2rem; text-align: center; }
        .pg-form label { display: block; margin-block-end: .25rem; font-weight: 600; font-size: .9rem; }
        .pg-form input[type=text], .pg-form input[type=email], .pg-form textarea {
            width: 100%; padding: .55rem .7rem; border: 1px solid var(--pg-border); border-radius: 8px;
            font-family: inherit; font-size: .95rem; background: #fff; color: var(--pg-text);
        }
        .pg-form textarea { min-height: 7rem; }
        .pg-form .field { margin-block-end: .9rem; }
        .pg-btn { display: inline-block; background: var(--pg-primary); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .95rem; cursor: pointer; }
        .pg-btn:hover { filter: brightness(1.1); text-decoration: none; }
        .pg-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .pg-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .pg-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .page-body { font-size: 1.02rem; }
        .page-body pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: 8px; overflow-x: auto; direction: ltr; }
        .page-body code { background: #f1f5f9; border-radius: 4px; padding-inline: .3rem; }
        .page-body pre code { background: none; padding: 0; }
        .page-body blockquote { border-inline-start: 3px solid var(--pg-primary); margin-inline-start: 0; padding-inline-start: 1rem; color: var(--pg-muted); }
        .page-body img { max-width: 100%; height: auto; border-radius: 8px; }
        .comment { border-block-end: 1px solid var(--pg-border); padding-block: .8rem; }
        .comment:last-child { border-block-end: 0; }
        .comment-children { margin-inline-start: 1.5rem; border-inline-start: 2px solid var(--pg-border); padding-inline-start: 1rem; }
        .comment-meta { color: var(--pg-muted); font-size: .8rem; }
        .muted { color: var(--pg-muted); }
        .page-item h2 { margin-block: 0 0.35rem; font-size: 1.2rem; }
        .page-item p { margin-block: 0 0.5rem; }
    </style>
    @endverbatim
</head>
<body>
    <header class="pg-header">
        <div class="pg-container pg-nav">
            <a class="brand" href="/pages">{{ trans('content::messages.pages_title') }}</a>
            <a href="/pages">{{ trans('content::messages.nav_home') }}</a>
            <a href="/pages-sitemap.xml">{{ trans('content::messages.nav_sitemap') }}</a>
        </div>
    </header>

    <main class="pg-main">
        <div class="pg-container">
            @yield('content')
        </div>
    </main>

    <footer class="pg-footer">
        <div class="pg-container">
            {{ trans('content::messages.powered_by') }}
        </div>
    </footer>
</body>
</html>
