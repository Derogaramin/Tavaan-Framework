<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? trans('forum::messages.admin_title') }}</title>
    @verbatim
    <style>
        :root { --p: #0e7490; --t: #0f172a; --m: #64748b; --bg: #f1f5f9; --card: #fff; --b: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--bg); color: var(--t); line-height: 1.8; }
        a { color: var(--p); text-decoration: none; }
        header { background: #0f172a; color: #f1f5f9; padding: .8rem 1.25rem; display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap; border-block-end: 3px solid var(--p); }
        header a { color: #67e8f9; }
        header .brand { font-weight: 700; color: #fff; }
        main { max-width: 1150px; margin-inline: auto; padding: 1.5rem 1rem; }
        .card { background: var(--card); border: 1px solid var(--b); border-radius: 10px; padding: 1.25rem; margin-block-end: 1rem; }
        .flash-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .flash-err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: .55rem .6rem; border-block-end: 1px solid var(--b); font-size: .9rem; }
        th { color: var(--m); }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .75rem; }
        .badge.pending { background: #fffbeb; color: #92400e; }
        .badge.locked { background: #e2e8f0; color: #334155; }
        .badge.pinned { background: #fef3c7; color: #92400e; }
        .badge.solved { background: #d1fae5; color: #065f46; }
        .badge.banned { background: #fef2f2; color: #991b1b; }
        .btn { display: inline-block; background: var(--p); color: #fff; border: 0; border-radius: 8px; padding: .45rem 1rem; font-family: inherit; font-size: .88rem; cursor: pointer; }
        .btn.gray { background: #475569; }
        .btn.red { background: #dc2626; }
        .btn.green { background: #059669; }
        .btn.amber { background: #d97706; }
        label { display: block; font-weight: 600; font-size: .88rem; margin-block-end: .2rem; }
        input[type=text], input[type=number], textarea, select {
            width: 100%; padding: .5rem .65rem; border: 1px solid var(--b); border-radius: 8px; font-family: inherit; font-size: .92rem;
        }
        textarea { min-height: 6rem; }
        .field { margin-block-end: .9rem; }
        .grid2 { display: grid; grid-template-columns: 1fr; gap: 0 1rem; }
        @media (min-width: 768px) { .grid2 { grid-template-columns: 1fr 1fr; } }
        .muted { color: var(--m); }
        form.inline { display: inline; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .pagination { display: flex; gap: .4rem; margin-block-start: 1rem; }
        .pagination a, .pagination span { border: 1px solid var(--b); background: #fff; border-radius: 6px; padding-inline: .6rem; }
        .pagination .current { background: var(--p); color: #fff; border-color: var(--p); }
        .row-actions { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
        .flag-reason { background: #fffbeb; border-radius: 6px; padding: .4rem .7rem; font-size: .85rem; }
    </style>
    @endverbatim
</head>
<body>
    @php
        $adminOk = \Tavaan\Core\Facade\Facades\Session::pull('status');
        $adminErr = \Tavaan\Core\Facade\Facades\Session::pull('error');
    @endphp
    <header>
        <span class="brand">💬 {{ trans('forum::messages.admin_title') }}</span>
        <a href="/admin/forum/forums">{{ trans('forum::messages.admin_forums') }}</a>
        <a href="/admin/forum/threads">{{ trans('forum::messages.admin_threads') }}</a>
        <a href="/admin/forum/moderation">{{ trans('forum::messages.admin_moderation') }}</a>
        <a href="/forums" target="_blank">{{ trans('forum::messages.view_site') }}</a>
    </header>
    <main>
        @if(is_string($adminOk) && $adminOk !== '')
            <div class="flash-ok">{{ $adminOk }}</div>
        @endif
        @if(is_string($adminErr) && $adminErr !== '')
            <div class="flash-err">{{ $adminErr }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
