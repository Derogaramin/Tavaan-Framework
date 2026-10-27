<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('poll::messages.polls_title'))</title>
    @verbatim
    <style>
        /* پوستهٔ پایهٔ ماژول نظرسنجی — RTL با CSS logical properties (DOC-090 §7). */
        :root { --pl-primary: #4f46e5; --pl-text: #0f172a; --pl-muted: #64748b; --pl-bg: #f8fafc; --pl-card: #ffffff; --pl-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--pl-bg); color: var(--pl-text); line-height: 1.9; }
        a { color: var(--pl-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .pl-header { background: var(--pl-card); border-block-end: 1px solid var(--pl-border); padding-block: 1rem; }
        .pl-container { max-width: 860px; margin-inline: auto; padding-inline: 1rem; }
        .pl-nav { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .pl-nav .brand { font-weight: 700; font-size: 1.15rem; color: var(--pl-text); }
        .pl-main { padding-block: 1.5rem; }
        .pl-card { background: var(--pl-card); border: 1px solid var(--pl-border); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .pl-meta { color: var(--pl-muted); font-size: .85rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .pl-footer { border-block-start: 1px solid var(--pl-border); color: var(--pl-muted); font-size: .85rem; padding-block: 1.5rem; margin-block-start: 2rem; text-align: center; }
        .pl-form .option-line { display: flex; align-items: center; gap: .6rem; padding-block: .45rem; border-block-end: 1px dashed var(--pl-border); }
        .pl-form .option-line:last-of-type { border-block-end: 0; }
        .pl-form .option-line input[type=radio], .pl-form .option-line input[type=checkbox] { width: auto; margin: 0; }
        .pl-btn { display: inline-block; background: var(--pl-primary); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .95rem; cursor: pointer; margin-block-start: .8rem; }
        .pl-btn:hover { filter: brightness(1.1); text-decoration: none; }
        .pl-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .pl-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .pl-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .75rem; }
        .badge.open { background: #ecfdf5; color: #065f46; }
        .badge.draft { background: #f1f5f9; color: #475569; }
        .badge.closed { background: #fef2f2; color: #991b1b; }
        .result-row { margin-block-end: .9rem; }
        .result-label { display: flex; justify-content: space-between; gap: .5rem; font-size: .92rem; }
        .result-bar { background: #eef2ff; border-radius: 999px; height: .85rem; overflow: hidden; margin-block-start: .25rem; }
        .result-fill { background: var(--pl-primary); height: 100%; border-radius: 999px; }
        .result-row.leader .result-fill { background: #059669; }
        .muted { color: var(--pl-muted); }
    </style>
    @endverbatim
</head>
<body>
    <header class="pl-header">
        <div class="pl-container pl-nav">
            <a class="brand" href="/polls">📊 {{ trans('poll::messages.polls_title') }}</a>
            <a href="/polls">{{ trans('poll::messages.nav_home') }}</a>
        </div>
    </header>

    <main class="pl-main">
        <div class="pl-container">
            @yield('content')
        </div>
    </main>

    <footer class="pl-footer">
        <div class="pl-container">
            {{ trans('poll::messages.powered_by') }}
        </div>
    </footer>
</body>
</html>
