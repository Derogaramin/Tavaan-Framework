<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('lms::messages.lms_title'))</title>
    @yield('metaTags')
    @stack('head')
    <link rel="stylesheet" href="/assets/kits/lms/css/app.css">
    @verbatim
    <style>
        /* پوستهٔ کیت LMS (kits/lms/themes/default) — override ماژول بدون
           تغییر کد/منطق (AC-APP-04). RTL با CSS logical properties.
           رنگ پایه emerald/teal — جزئیات تکمیلی در assets/css/app.css کیت. */
        :root { --l-primary: #0d9488; --l-text: #134e4a; --l-muted: #64748b; --l-bg: #f0fdfa; --l-card: #fff; --l-border: #d7e8e4; --l-success: #16a34a; --l-accent: #f59e0b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--l-bg); color: var(--l-text); line-height: 1.9; }
        a { color: var(--l-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .lms-header { background: linear-gradient(180deg, #115e59 0%, #134e4a 100%); color: #ccfbf1; border-block-end: 4px solid var(--l-accent); padding-block: .9rem; }
        .lms-container { max-width: 1080px; margin-inline: auto; padding-inline: 1rem; }
        .lms-nav { display: flex; gap: 1.1rem; align-items: center; flex-wrap: wrap; }
        .lms-nav a { color: #99f6e4; }
        .lms-nav .brand { font-weight: 800; font-size: 1.25rem; color: #fff; }
        .lms-nav .spacer { flex: 1; }
        .lms-main { padding-block: 1.5rem; }
        .lms-card { background: var(--l-card); border: 1px solid var(--l-border); border-radius: 12px; padding: 1.1rem 1.25rem; margin-block-end: .9rem; box-shadow: 0 1px 2px rgb(19 78 74 / 5%); }
        .lms-card h2, .lms-card h3 { margin-block: .2rem .5rem; }
        .lms-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) { .lms-grid.cols-3 { grid-template-columns: repeat(3, 1fr); } .lms-grid.cols-2 { grid-template-columns: 2.4fr 1fr; } }
        .muted { color: var(--l-muted); font-size: .85rem; }
        .badge { display: inline-block; border-radius: 999px; padding-inline: .55rem; font-size: .74rem; font-weight: 700; background: #ccfbf1; color: #115e59; }
        .badge.free { background: #dcfce7; color: #14532d; }
        .badge.paid { background: #fef3c7; color: #92400e; }
        .badge.level { background: #ccfbf1; color: #115e59; }
        .badge.done { background: #dcfce7; color: #14532d; }
        .badge.progress { background: #cffafe; color: #155e75; }
        .badge.pending { background: #fee2e2; color: #991b1b; }
        .badge.revoked { background: #fecaca; color: #7f1d1d; }
        .lms-btn { display: inline-block; border: 0; cursor: pointer; background: var(--l-primary); color: #fff; padding: .55rem 1.2rem; border-radius: 10px; font-family: inherit; font-size: .92rem; font-weight: 700; }
        .lms-btn:hover { filter: brightness(1.08); text-decoration: none; }
        .lms-btn.ghost { background: transparent; color: #0f766e; border: 1px solid var(--l-primary); }
        .lms-btn.success { background: var(--l-success); }
        .lms-btn.sm { padding: .25rem .7rem; font-size: .8rem; }
        .lms-btn.danger { background: #dc2626; }
        .progress-track { background: #ccfbf1; border-radius: 999px; height: .55rem; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #0d9488, #5eead4); height: 100%; border-radius: 999px; }
        .flash { padding: .6rem .9rem; border-radius: 9px; margin-block-end: .8rem; font-size: .9rem; }
        .flash.success { background: #dcfce7; color: #14532d; }
        .flash.error { background: #fee2e2; color: #7f1d1d; }
        .lms-form .field { margin-block-end: .7rem; display: flex; flex-direction: column; gap: .25rem; }
        .lms-form label { font-size: .85rem; font-weight: 700; }
        .lms-form input[type=text], .lms-form input[type=number], .lms-form select, .lms-form textarea { padding: .5rem; border: 1px solid var(--l-border); border-radius: 8px; font-family: inherit; font-size: .92rem; width: 100%; }
        .lms-form textarea { min-height: 7rem; }
        .lesson-row { display: flex; gap: .8rem; align-items: center; border-block-end: 1px solid var(--l-border); padding-block: .55rem; }
        .lesson-row:last-child { border-block-end: 0; }
        .lesson-row .grow { flex: 1; }
        .lesson-row .meta { color: var(--l-muted); font-size: .8rem; text-align: end; min-width: 7rem; }
        .qa-item { border-block-end: 1px dashed var(--l-border); padding-block: .6rem; }
        .qa-item:last-child { border-block-end: 0; }
        .qa-item .who { font-weight: 700; font-size: .85rem; }
        .qa-reply { margin-inline-start: 1.4rem; border-inline-start: 3px solid var(--l-border); padding-inline-start: .8rem; }
        .lms-pagination { display: flex; gap: .35rem; margin-block-start: .9rem; flex-wrap: wrap; }
        .lms-pagination a, .lms-pagination span { padding: .2rem .65rem; border: 1px solid var(--l-border); border-radius: 8px; background: #fff; font-size: .82rem; }
        .lms-pagination span.current { background: var(--l-primary); color: #fff; border-color: var(--l-primary); }
        .lms-footer { border-block-start: 1px solid var(--l-border); margin-block-start: 2rem; padding-block: 1rem; color: var(--l-muted); font-size: .82rem; text-align: center; }
    </style>
    @endverbatim
</head>
<body>
<header class="lms-header">
    <div class="lms-container lms-nav">
        <a class="brand" href="/lms">🎓 {{ trans('lms::messages.lms_title') }}</a>
        <a href="/lms">{{ trans('lms::messages.catalog') }}</a>
        <a href="/lms/learn">{{ trans('lms::messages.my_courses') }}</a>
        <span class="spacer"></span>
        <a href="/lms/verify-certificate">{{ trans('lms::messages.verify_certificate') }}</a>
        @php $me = \Tavaan\Core\Facade\Facades\Auth::user(); @endphp
        @auth
            <span style="color:#99f6e4; font-size:.85rem">{{ $me->name ?? '' }}</span>
        @endauth
    </div>
</header>
<main class="lms-main">
    <div class="lms-container">
        @if(($flash = session('status')) !== null)
            <div class="flash success">{{ is_string($flash) ? trans('lms::flash.' . $flash) : '' }}</div>
        @endif
        @if(($error = session('error')) !== null)
            <div class="flash error">{{ is_string($error) ? $error : '' }}</div>
        @endif
        @if(($errors = session('errors')) !== null && is_array($errors))
            <div class="flash error">
                @foreach($errors as $fieldErrors)
                    @foreach((array) $fieldErrors as $msg)
                        <div>{{ $msg }}</div>
                    @endforeach
                @endforeach
            </div>
        @endif
        @yield('content')
    </div>
</main>
<footer class="lms-footer">
    <div class="lms-container">{{ trans('lms::messages.lms_title') }} — {{ config('app.name', 'Tavaan') }} • نیروگرفته از تَوان 🚀</div>
</footer>
</body>
</html>
