<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('faq::messages.index_title'))</title>
    @verbatim
    <style>
        /* پوستهٔ پایهٔ ماژول پرسش‌های متداول — RTL با CSS logical properties (DOC-090 §7). */
        :root { --fq-primary: #0d9488; --fq-text: #0f172a; --fq-muted: #64748b; --fq-bg: #f8fafc; --fq-card: #ffffff; --fq-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--fq-bg); color: var(--fq-text); line-height: 1.9; }
        a { color: var(--fq-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .fq-header { background: var(--fq-card); border-block-end: 1px solid var(--fq-border); padding-block: 1rem; }
        .fq-container { max-width: 860px; margin-inline: auto; padding-inline: 1rem; }
        .fq-nav { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .fq-nav .brand { font-weight: 700; font-size: 1.15rem; color: var(--fq-text); }
        .fq-main { padding-block: 1.5rem; }
        .fq-card { background: var(--fq-card); border: 1px solid var(--fq-border); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .fq-footer { border-block-start: 1px solid var(--fq-border); color: var(--fq-muted); font-size: .85rem; padding-block: 1.5rem; margin-block-start: 2rem; text-align: center; }
        .fq-search { display: flex; gap: .5rem; flex-wrap: wrap; }
        .fq-search input[type=search] { flex: 1 1 220px; padding: .5rem .65rem; border: 1px solid var(--fq-border); border-radius: 8px; font-family: inherit; font-size: .95rem; }
        .fq-chips { display: flex; gap: .5rem; flex-wrap: wrap; margin-block: 1rem; }
        .fq-chip { border: 1px solid var(--fq-border); background: var(--fq-card); border-radius: 999px; padding: .25rem .9rem; font-size: .88rem; }
        .fq-chip.active { background: var(--fq-primary); border-color: var(--fq-primary); color: #fff; }
        .fq-chip.active:hover { text-decoration: none; }
        .fq-cat-title { font-size: 1.1rem; margin-block: 1.5rem .5rem; }
        .fq-item { background: var(--fq-card); border: 1px solid var(--fq-border); border-radius: 10px; margin-block-end: .6rem; overflow: hidden; }
        .fq-item summary { cursor: pointer; padding: .8rem 1rem; font-weight: 600; list-style-position: inside; }
        .fq-item summary:hover { background: #f0fdfa; }
        .fq-item[open] summary { border-block-end: 1px dashed var(--fq-border); }
        .fq-answer { padding: .9rem 1rem; }
        .fq-answer p { margin-block: .4rem; }
        .fq-helpful { border-block-start: 1px dashed var(--fq-border); padding: .6rem 1rem; display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; color: var(--fq-muted); font-size: .85rem; }
        .fq-btn { display: inline-block; background: var(--fq-primary); color: #fff; border: 0; border-radius: 8px; padding: .5rem 1.1rem; font-family: inherit; font-size: .92rem; cursor: pointer; }
        .fq-btn:hover { filter: brightness(1.1); text-decoration: none; }
        .fq-btn.ghost { background: var(--fq-card); color: var(--fq-text); border: 1px solid var(--fq-border); }
        .fq-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .fq-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .fq-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .muted { color: var(--fq-muted); }
    </style>
    @endverbatim
</head>
<body>
    <header class="fq-header">
        <div class="fq-container fq-nav">
            <a class="brand" href="/faq">❓ {{ trans('faq::messages.faqs_title') }}</a>
            <a href="/faq">{{ trans('faq::messages.nav_home') }}</a>
        </div>
    </header>

    <main class="fq-main">
        <div class="fq-container">
            @yield('content')
        </div>
    </main>

    <footer class="fq-footer">
        <div class="fq-container">
            {{ trans('faq::messages.powered_by') }}
        </div>
    </footer>
</body>
</html>
