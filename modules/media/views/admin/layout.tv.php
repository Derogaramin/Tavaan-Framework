<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? trans('media::messages.ui.admin_title') }}</title>
    @verbatim
    <style>
        :root { --p: #2563eb; --t: #0f172a; --m: #64748b; --bg: #f1f5f9; --card: #fff; --b: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--bg); color: var(--t); line-height: 1.8; }
        a { color: var(--p); text-decoration: none; }
        header { background: #0f172a; color: #e2e8f0; padding: .8rem 1.25rem; display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap; }
        header a { color: #93c5fd; }
        header .brand { font-weight: 700; color: #fff; }
        main { max-width: 1200px; margin-inline: auto; padding: 1.5rem 1rem; }
        .card { background: var(--card); border: 1px solid var(--b); border-radius: 12px; padding: 1.25rem; margin-block-end: 1rem; }
        .flash-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; }
        .btn { display: inline-block; background: var(--p); color: #fff; border: 0; border-radius: 8px; padding: .5rem 1.1rem; font-family: inherit; font-size: .9rem; cursor: pointer; }
        .btn.gray { background: #475569; }
        .btn.red { background: #dc2626; }
        .toolbar { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; margin-block-end: 1rem; }
        .toolbar input[type=search], .toolbar select { padding: .5rem .65rem; border: 1px solid var(--b); border-radius: 8px; font-family: inherit; font-size: .92rem; }
        .toolbar input[type=search] { min-width: 240px; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .9rem; }
        .media-card { border: 1px solid var(--b); border-radius: 10px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
        .media-thumb { height: 120px; display: flex; align-items: center; justify-content: center; background: #f8fafc; font-size: 2.2rem; overflow: hidden; }
        .media-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .media-body { padding: .55rem .7rem; font-size: .78rem; }
        .media-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: .82rem; }
        .muted { color: var(--m); }
        .media-actions { display: flex; gap: .3rem; padding: .4rem .7rem .7rem; }
        .media-actions form { flex: 1; }
        .btn.tiny { padding: .22rem .55rem; font-size: .72rem; width: 100%; }
        .upload-box { border: 2px dashed var(--b); border-radius: 10px; padding: 1rem; display: flex; gap: .8rem; align-items: center; flex-wrap: wrap; }
        .upload-box input[type=file] { flex: 1; min-width: 220px; }
        .pagination { display: flex; gap: .4rem; margin-block-start: 1rem; }
        .pagination a, .pagination span { border: 1px solid var(--b); background: #fff; border-radius: 6px; padding-inline: .6rem; }
        .pagination .current { background: var(--p); color: #fff; border-color: var(--p); }
        .inline { display: inline; }
    </style>
    @endverbatim
</head>
<body>
    <header>
        <span class="brand">{{ trans('media::messages.ui.admin_title') }}</span>
        <a href="/admin">{{ trans('media::messages.ui.dashboard') }}</a>
        <a href="/admin/media">{{ trans('media::messages.ui.items_count') }}</a>
        <a href="/admin/users">{{ trans('core-user::messages.ui.users_count') }}</a>
        <a href="/" target="_blank">{{ trans('media::messages.ui.view_site') }}</a>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
