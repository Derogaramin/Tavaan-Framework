<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('forum::messages.forum_title'))</title>
    @yield('metaTags')
    @stack('head')
    <link rel="stylesheet" href="/assets/kits/forum/css/app.css">
    @verbatim
    <style>
        /* پوستهٔ کیت انجمن (kits/forum/themes/default) — override ماژول بدون
           تغییر کد/منطق (AC-APP-04). RTL با CSS logical properties.
           رنگ پایه indigo — جزئیات تکمیلی در assets/css/app.css کیت. */
        :root { --f-primary: #4f46e5; --f-text: #1e1b4b; --f-muted: #6b7280; --f-bg: #f5f6ff; --f-card: #fff; --f-border: #e0e2f5; --f-solution: #16a34a; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--f-bg); color: var(--f-text); line-height: 1.9; }
        a { color: var(--f-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .forum-header { background: linear-gradient(180deg, #312e81 0%, #1e1b4b 100%); color: #f1f5f9; border-block-end: 4px solid var(--f-primary); padding-block: .9rem; }
        .forum-container { max-width: 1080px; margin-inline: auto; padding-inline: 1rem; }
        .forum-nav { display: flex; gap: 1.1rem; align-items: center; flex-wrap: wrap; }
        .forum-nav a { color: #c7d2fe; }
        .forum-nav .brand { font-weight: 800; font-size: 1.25rem; color: #fff; }
        .forum-nav .spacer { flex: 1; }
        .forum-main { padding-block: 1.5rem; }
        .forum-layout { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 768px) { .forum-layout { grid-template-columns: 2.4fr 1fr; } }
        .forum-card { background: var(--f-card); border: 1px solid var(--f-border); border-radius: 10px; padding: 1.1rem 1.25rem; margin-block-end: .9rem; }
        .forum-card h2, .forum-card h3 { margin-block: .2rem .5rem; }
        .forum-meta { color: var(--f-muted); font-size: .83rem; display: flex; gap: .8rem; flex-wrap: wrap; align-items: center; }
        .forum-row { display: flex; gap: .9rem; align-items: center; border-block-end: 1px solid var(--f-border); padding-block: .7rem; }
        .forum-row:last-child { border-block-end: 0; }
        .forum-row .icon { font-size: 1.5rem; }
        .forum-row .grow { flex: 1; }
        .forum-row .counts { color: var(--f-muted); font-size: .8rem; text-align: end; min-width: 6.5rem; }
        .badge { border-radius: 999px; padding-inline: .55rem; font-size: .74rem; font-weight: 700; }
        .badge.pinned { background: #fef3c7; color: #92400e; }
        .badge.locked { background: #e2e8f0; color: #334155; }
        .badge.solved { background: #d1fae5; color: #065f46; }
        .badge.new { background: #cffafe; color: #155e75; }
        .badge.karma { background: #ede9fe; color: #5b21b6; }
        .post { border: 1px solid var(--f-border); border-radius: 10px; background: var(--f-card); margin-block-end: .9rem; }
        .post.solution { border-color: var(--f-solution); box-shadow: 0 0 0 1px var(--f-solution); }
        .post-head { display: flex; gap: .8rem; align-items: center; padding: .6rem 1rem; border-block-end: 1px solid var(--f-border); background: #f8fafc; border-radius: 10px 10px 0 0; flex-wrap: wrap; }
        .post-head .author { font-weight: 700; }
        .post-head .date { color: var(--f-muted); font-size: .8rem; }
        .post-body { padding: 1rem; font-size: .98rem; overflow-wrap: anywhere; }
        .post-body pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: 8px; overflow-x: auto; direction: ltr; }
        .post-body code { background: #f1f5f9; border-radius: 4px; padding-inline: .3rem; }
        .post-body pre code { background: none; padding: 0; }
        .post-body blockquote { border-inline-start: 3px solid var(--f-primary); margin-inline-start: 0; padding-inline-start: 1rem; color: var(--f-muted); }
        .post-actions { display: flex; gap: .5rem; padding: .55rem 1rem; border-block-start: 1px solid var(--f-border); align-items: center; flex-wrap: wrap; }
        .vote-box { display: flex; gap: .35rem; align-items: center; }
        .vote-btn { border: 1px solid var(--f-border); background: #fff; border-radius: 6px; padding-inline: .55rem; cursor: pointer; font-family: inherit; }
        .vote-btn.active-up { background: #d1fae5; border-color: var(--f-solution); }
        .vote-btn.active-down { background: #fee2e2; border-color: #dc2626; }
        .vote-score { font-weight: 700; min-width: 1.6rem; text-align: center; }
        .btn-link { border: 0; background: none; color: var(--f-primary); cursor: pointer; font-family: inherit; font-size: .85rem; padding: 0; }
        .btn-link:hover { text-decoration: underline; }
        .forum-form label { display: block; margin-block-end: .25rem; font-weight: 600; font-size: .9rem; }
        .forum-form input[type=text], .forum-form textarea, .forum-form select {
            width: 100%; padding: .55rem .7rem; border: 1px solid var(--f-border); border-radius: 8px; font-family: inherit; font-size: .95rem; background: #fff; color: var(--f-text);
        }
        .forum-form textarea { min-height: 9rem; }
        .forum-form .field { margin-block-end: .9rem; }
        .hp-field { position: absolute; inset-inline-start: -9999px; opacity: 0; height: 0; overflow: hidden; }
        .forum-btn { display: inline-block; background: var(--f-primary); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .92rem; cursor: pointer; }
        .forum-btn:hover { filter: brightness(1.12); text-decoration: none; }
        .forum-btn.gray { background: #475569; }
        .forum-btn.green { background: var(--f-solution); }
        .forum-flash { border-radius: 8px; padding: .7rem 1rem; margin-block-end: 1rem; }
        .forum-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .forum-flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .forum-pagination { display: flex; gap: .5rem; justify-content: center; margin-block: 1.5rem; flex-wrap: wrap; }
        .forum-pagination a, .forum-pagination span { border: 1px solid var(--f-border); border-radius: 8px; padding-inline: .7rem; padding-block: .2rem; background: var(--f-card); }
        .forum-pagination .current { background: var(--f-primary); color: #fff; border-color: var(--f-primary); }
        .forum-footer { background: #1e1b4b; color: #a5b4fc; padding-block: 1.4rem; margin-block-start: 2.5rem; text-align: center; font-size: .85rem; }
        .forum-footer a { color: #e0e7ff; }
        .muted { color: var(--f-muted); }
        form.inline { display: inline; }
        .member-list { list-style: none; margin: 0; padding: 0; }
        .member-list li { display: flex; justify-content: space-between; gap: .5rem; padding-block: .3rem; border-block-end: 1px dashed var(--f-border); }
        .member-list li:last-child { border-block-end: 0; }
        .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; margin-inline-end: .3rem; }
        .live-off .live-dot { background: #94a3b8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: start; padding: .5rem .6rem; border-block-end: 1px solid var(--f-border); font-size: .9rem; }
        th { color: var(--f-muted); }
    </style>
    @endverbatim
</head>
<body>
    @php
        $flashOk = \Tavaan\Core\Facade\Facades\Session::pull('status');
        $flashErr = \Tavaan\Core\Facade\Facades\Session::pull('error');
        $me = \Tavaan\Core\Facade\Facades\Auth::user();
        $myUsername = '';
        if ($me !== null) {
            try {
                $myUsername = app()->make(\Modules\Forum\Application\UseCases\ProfileService::class)
                    ->usernameOf((int) $me->getAuthIdentifier());
            } catch (\Throwable) {
                $myUsername = '';
            }
        }
    @endphp
    <header class="forum-header">
        <div class="forum-container forum-nav">
            <a class="brand" href="/forums">💬 {{ trans('forum::messages.forum_title') }}</a>
            <a href="/forums">{{ trans('forum::messages.nav_forums') }}</a>
            <a href="/forums/search">{{ trans('forum::messages.nav_search') }}</a>
            <span class="spacer"></span>
            @auth
                @if($myUsername !== '')
                    <a href="/members/{{ $myUsername }}">👤 {{ $myUsername }}</a>
                @endif
            @endauth
            @guest
                <a href="/login">{{ trans('forum::messages.nav_login') }}</a>
            @endguest
        </div>
    </header>

    <main class="forum-main">
        <div class="forum-container">
            @if(is_string($flashOk) && $flashOk !== '')
                <div class="forum-flash ok">{{ $flashOk }}</div>
            @endif
            @if(is_string($flashErr) && $flashErr !== '')
                <div class="forum-flash error">{{ $flashErr }}</div>
            @endif
            @yield('content')
        </div>
    </main>

    <footer class="forum-footer">
        <div class="forum-container">
            {{ trans('forum::messages.powered_by') }} — {{ jalali(null, 'Y/m/d') }}
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
