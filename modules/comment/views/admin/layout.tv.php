<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? trans('comment::messages.ui.admin_title') }}</title>
    @verbatim
    <style>
        :root { --p: #2563eb; --t: #0f172a; --m: #64748b; --bg: #f1f5f9; --card: #fff; --b: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--bg); color: var(--t); line-height: 1.8; }
        a { color: var(--p); text-decoration: none; }
        header { background: #0f172a; color: #e2e8f0; padding: .8rem 1.25rem; display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap; }
        header a { color: #93c5fd; }
        header .brand { font-weight: 700; color: #fff; }
        main { max-width: 1100px; margin-inline: auto; padding: 1.5rem 1rem; }
        .card { background: var(--card); border: 1px solid var(--b); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .flash-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: .55rem .6rem; border-block-end: 1px solid var(--b); font-size: .9rem; vertical-align: top; }
        th { color: var(--m); }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .75rem; white-space: nowrap; }
        .badge.approved { background: #ecfdf5; color: #065f46; }
        .badge.pending { background: #fffbeb; color: #92400e; }
        .badge.spam { background: #fef2f2; color: #991b1b; }
        .btn { display: inline-block; background: var(--p); color: #fff; border: 0; border-radius: 8px; padding: .4rem .9rem; font-family: inherit; font-size: .82rem; cursor: pointer; }
        .btn.gray { background: #475569; }
        .btn.red { background: #dc2626; }
        .btn.green { background: #059669; }
        .btn.amber { background: #d97706; }
        select, input[type=search] { padding: .45rem .6rem; border: 1px solid var(--b); border-radius: 8px; font-family: inherit; font-size: .9rem; }
        .muted { color: var(--m); }
        .inline { display: inline; }
        .row-actions { display: flex; gap: .35rem; flex-wrap: wrap; align-items: center; }
        .toolbar { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; margin-block-end: 1rem; }
        .comment-body { max-width: 340px; white-space: pre-wrap; word-break: break-word; font-size: .85rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: .7rem; margin-block-end: 1rem; }
        .stat { background: #f8fafc; border: 1px solid var(--b); border-radius: 10px; padding: .6rem .9rem; text-align: center; }
        .stat .num { font-size: 1.35rem; font-weight: 800; }
        .pagination { display: flex; gap: .4rem; margin-block-start: 1rem; }
        .pagination a, .pagination span { border: 1px solid var(--b); background: #fff; border-radius: 6px; padding-inline: .6rem; }
        .pagination .current { background: var(--p); color: #fff; border-color: var(--p); }
    </style>
    @endverbatim
</head>
<body>
    <header>
        <span class="brand">{{ trans('comment::messages.ui.admin_title') }}</span>
        <a href="/admin">{{ trans('comment::messages.ui.dashboard') }}</a>
        <a href="/admin/comments">{{ trans('comment::messages.ui.queue') }}</a>
        <a href="/admin/users">{{ trans('core-user::messages.ui.users_count') }}</a>
        <a href="/" target="_blank">{{ trans('comment::messages.ui.view_site') }}</a>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
