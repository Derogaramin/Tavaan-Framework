<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', trans('crm::messages.support_portal'))</title>
    @stack('head')
    @verbatim
    <style>
        :root { --c-primary: #0284c7; --c-text: #0f172a; --c-muted: #64748b; --c-bg: #f0f9ff; --c-card: #fff; --c-border: #dbeafe; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--c-bg); color: var(--c-text); line-height: 1.9; }
        a { color: var(--c-primary); text-decoration: none; }
        .crm-header { background: linear-gradient(180deg, #0c4a6e 0%, #082f49 100%); color: #e0f2fe; padding-block: .9rem; border-block-end: 4px solid var(--c-primary); }
        .crm-container { max-width: 860px; margin-inline: auto; padding-inline: 1rem; }
        .crm-nav { display: flex; gap: 1.1rem; align-items: center; flex-wrap: wrap; }
        .crm-nav .brand { font-weight: 800; font-size: 1.2rem; color: #fff; }
        .crm-nav a { color: #bae6fd; }
        .crm-main { padding-block: 1.5rem; }
        .crm-card { background: var(--c-card); border: 1px solid var(--c-border); border-radius: 12px; padding: 1.2rem 1.4rem; margin-block-end: 1rem; }
        .crm-form .field { margin-block-end: .8rem; display: flex; flex-direction: column; gap: .3rem; }
        .crm-form label { font-weight: 700; font-size: .88rem; }
        .crm-form input, .crm-form select, .crm-form textarea { padding: .55rem; border: 1px solid var(--c-border); border-radius: 9px; font-family: inherit; font-size: .95rem; width: 100%; }
        .crm-form textarea { min-height: 8rem; }
        .crm-btn { display: inline-block; border: 0; cursor: pointer; background: var(--c-primary); color: #fff; padding: .6rem 1.3rem; border-radius: 10px; font-family: inherit; font-size: .95rem; font-weight: 700; }
        .crm-btn.ghost { background: transparent; color: var(--c-primary); border: 1px solid var(--c-primary); }
        .badge { display: inline-block; border-radius: 999px; padding-inline: .6rem; font-size: .75rem; font-weight: 700; background: #e0f2fe; color: #075985; }
        .badge.green { background: #dcfce7; color: #14532d; }
        .badge.red { background: #fee2e2; color: #991b1b; }
        .badge.amber { background: #fef3c7; color: #92400e; }
        .muted { color: var(--c-muted); font-size: .85rem; }
        .flash { padding: .6rem .9rem; border-radius: 9px; margin-block-end: .8rem; font-size: .9rem; }
        .flash.success { background: #dcfce7; color: #14532d; }
        .flash.error { background: #fee2e2; color: #7f1d1d; }
        .reply { border-block-end: 1px dashed var(--c-border); padding-block: .7rem; }
        .reply:last-child { border-block-end: 0; }
        .reply.agent { background: #f0f9ff; border-radius: 10px; padding-inline: .9rem; }
        .crm-footer { border-block-start: 1px solid var(--c-border); margin-block-start: 2rem; padding-block: 1rem; color: var(--c-muted); font-size: .82rem; text-align: center; }
    </style>
    @endverbatim
</head>
<body>
<header class="crm-header">
    <div class="crm-container crm-nav">
        <a class="brand" href="/crm/support">🎧 {{ trans('crm::messages.support_portal') }}</a>
        <a href="/crm/support/track">{{ trans('crm::messages.support_track') }}</a>
        <a href="/">{{ config('app.name', 'Tavaan') }}</a>
    </div>
</header>
<main class="crm-main">
    <div class="crm-container">
        @if(($flash = session('status')) !== null)
            <div class="flash success">{{ is_string($flash) ? trans('crm::flash.' . $flash) : '' }}</div>
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
        @if(($ticketNumber = session('ticket_number')) !== null && is_string($ticketNumber))
            <div class="flash success">{{ trans('crm::messages.support_thanks') }} <strong dir="ltr">{{ $ticketNumber }}</strong></div>
        @endif
        @yield('content')
    </div>
</main>
<footer class="crm-footer">
    <div class="crm-container">{{ trans('crm::messages.crm_title') }} — {{ config('app.name', 'Tavaan') }}</div>
</footer>
</body>
</html>
