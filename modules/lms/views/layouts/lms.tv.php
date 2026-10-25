<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('lms::messages.lms_title'))</title>
    @yield('metaTags')
    @stack('head')
    @verbatim
    <style>
        /* پوستهٔ پایهٔ ماژول LMS — کیت theme کامل را override می‌کند (AC-APP-04).
           RTL با CSS logical properties (DOC-090 §7). */
        :root { --l-primary: #1d4ed8; --l-text: #0f172a; --l-muted: #64748b; --l-bg: #f6f8fc; --l-card: #fff; --l-border: #e2e8f0; --l-success: #16a34a; --l-accent: #f59e0b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--l-bg); color: var(--l-text); line-height: 1.9; }
        a { color: var(--l-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .lms-header { background: linear-gradient(180deg, #1e3a8a 0%, #172554 100%); color: #e2e8f0; border-block-end: 4px solid var(--l-primary); padding-block: .9rem; }
        .lms-container { max-width: 1080px; margin-inline: auto; padding-inline: 1rem; }
        .lms-nav { display: flex; gap: 1.1rem; align-items: center; flex-wrap: wrap; }
        .lms-nav a { color: #bfdbfe; }
        .lms-nav .brand { font-weight: 800; font-size: 1.25rem; color: #fff; }
        .lms-nav .spacer { flex: 1; }
        .lms-main { padding-block: 1.5rem; }
        .lms-card { background: var(--l-card); border: 1px solid var(--l-border); border-radius: 10px; padding: 1.1rem 1.25rem; margin-block-end: .9rem; }
        .lms-card h2, .lms-card h3 { margin-block: .2rem .5rem; }
        .lms-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) { .lms-grid.cols-3 { grid-template-columns: repeat(3, 1fr); } .lms-grid.cols-2 { grid-template-columns: 2.4fr 1fr; } }
        .muted { color: var(--l-muted); font-size: .85rem; }
        .badge { display: inline-block; border-radius: 999px; padding-inline: .55rem; font-size: .74rem; font-weight: 700; background: #e2e8f0; color: #334155; }
        .badge.free { background: #dcfce7; color: #14532d; }
        .badge.paid { background: #fef3c7; color: #92400e; }
        .badge.level { background: #dbeafe; color: #1e40af; }
        .badge.done { background: #dcfce7; color: #14532d; }
        .badge.progress { background: #e0e7ff; color: #3730a3; }
        .badge.pending { background: #fee2e2; color: #991b1b; }
        .badge.revoked { background: #fecaca; color: #7f1d1d; }
        .lms-btn { display: inline-block; border: 0; cursor: pointer; background: var(--l-primary); color: #fff; padding: .55rem 1.2rem; border-radius: 9px; font-family: inherit; font-size: .92rem; font-weight: 700; }
        .lms-btn:hover { filter: brightness(1.1); text-decoration: none; }
        .lms-btn.ghost { background: transparent; color: var(--l-primary); border: 1px solid var(--l-primary); }
        .lms-btn.success { background: var(--l-success); }
        .lms-btn.sm { padding: .25rem .7rem; font-size: .8rem; }
        .lms-btn.danger { background: #dc2626; }
        .progress-track { background: #e2e8f0; border-radius: 999px; height: .55rem; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, var(--l-primary), #60a5fa); height: 100%; border-radius: 999px; }
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
            <span style="color:#bfdbfe; font-size:.85rem">{{ $me->name ?? '' }}</span>
        @endauth
    </div>
</header>
<main class="lms-main">
    <div class="lms-container">
        @if(($flash = session('status')) !== null)
            <div class="flash success">{{ is_string($flash) && str_starts_with($flash, 'lms:') ? trans($flash) : trans('lms::flash.' . $flash) }}</div>
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
    <div class="lms-container">{{ trans('lms::messages.lms_title') }} — {{ config('app.name', 'Tavaan') }}</div>
</footer>
</body>
</html>
