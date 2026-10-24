<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? trans('blog::messages.admin_title') }}</title>
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
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: .55rem .6rem; border-block-end: 1px solid var(--b); font-size: .9rem; }
        th { color: var(--m); }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .75rem; }
        .badge.published, .badge.approved { background: #ecfdf5; color: #065f46; }
        .badge.draft, .badge.pending2 { background: #f1f5f9; color: #475569; }
        .badge.scheduled, .badge.pending { background: #fffbeb; color: #92400e; }
        .badge.archived, .badge.spam { background: #fef2f2; color: #991b1b; }
        .btn { display: inline-block; background: var(--p); color: #fff; border: 0; border-radius: 8px; padding: .5rem 1.1rem; font-family: inherit; font-size: .9rem; cursor: pointer; }
        .btn.gray { background: #475569; }
        .btn.red { background: #dc2626; }
        .btn.green { background: #059669; }
        label { display: block; font-weight: 600; font-size: .88rem; margin-block-end: .2rem; }
        input[type=text], input[type=email], input[type=datetime-local], textarea, select {
            width: 100%; padding: .5rem .65rem; border: 1px solid var(--b); border-radius: 8px; font-family: inherit; font-size: .92rem;
        }
        textarea { min-height: 14rem; }
        .field { margin-block-end: .9rem; }
        .grid2 { display: grid; grid-template-columns: 1fr; gap: 0 1rem; }
        @media (min-width: 768px) { .grid2 { grid-template-columns: 1fr 1fr; } }
        .muted { color: var(--m); }
        .inline { display: inline; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .pagination { display: flex; gap: .4rem; margin-block-start: 1rem; }
        .pagination a, .pagination span { border: 1px solid var(--b); background: #fff; border-radius: 6px; padding-inline: .6rem; }
        .pagination .current { background: var(--p); color: #fff; border-color: var(--p); }
        .row-actions { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
    </style>
    @endverbatim
</head>
<body>
    <header>
        <span class="brand">{{ trans('blog::messages.admin_title') }}</span>
        <a href="/admin/blog/posts">{{ trans('blog::messages.admin_posts') }}</a>
        <a href="/admin/blog/comments">{{ trans('blog::messages.admin_comments') }}</a>
        <a href="/admin/blog/posts/create">+ {{ trans('blog::messages.new_post') }}</a>
        <a href="/blog" target="_blank">{{ trans('blog::messages.view_site') }}</a>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
