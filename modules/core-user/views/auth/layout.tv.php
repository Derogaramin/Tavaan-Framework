<!DOCTYPE html>
<html lang="{{ config('app.locale', 'fa') }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? trans('core-user::messages.ui.app_name') }}</title>
    @verbatim
    <style>
        :root { --p: #2563eb; --t: #0f172a; --m: #64748b; --bg: #f1f5f9; --card: #fff; --b: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; background: var(--bg); color: var(--t); line-height: 1.8; min-height: 100vh; display: flex; flex-direction: column; }
        a { color: var(--p); text-decoration: none; }
        .auth-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .auth-card { background: var(--card); border: 1px solid var(--b); border-radius: 16px; padding: 2rem; width: 100%; max-width: 420px; box-shadow: 0 8px 30px rgba(15, 23, 42, .06); }
        .auth-card h1 { font-size: 1.25rem; margin: 0 0 1.25rem; text-align: center; }
        .brand { text-align: center; font-weight: 800; font-size: 1.5rem; color: var(--p); margin-block-end: .5rem; }
        label { display: block; font-weight: 600; font-size: .88rem; margin-block-end: .2rem; }
        input[type=text], input[type=email], input[type=password], input[type=tel], textarea {
            width: 100%; padding: .55rem .7rem; border: 1px solid var(--b); border-radius: 8px; font-family: inherit; font-size: .95rem; background: #fff;
        }
        input:focus, textarea:focus { outline: 2px solid #bfdbfe; border-color: var(--p); }
        .field { margin-block-end: .9rem; }
        .btn { display: inline-block; background: var(--p); color: #fff; border: 0; border-radius: 8px; padding: .55rem 1.2rem; font-family: inherit; font-size: .95rem; cursor: pointer; width: 100%; }
        .btn.gray { background: #475569; }
        .btn.red { background: #dc2626; }
        .btn.green { background: #059669; }
        .btn.small { width: auto; padding: .3rem .8rem; font-size: .8rem; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; font-size: .88rem; }
        .flash-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: .6rem 1rem; margin-block-end: 1rem; font-size: .88rem; }
        .muted { color: var(--m); }
        .hint { font-size: .78rem; color: var(--m); }
        .alt { text-align: center; margin-block-start: 1rem; font-size: .9rem; }
        .remember { display: flex; align-items: center; gap: .4rem; font-size: .9rem; margin-block-end: 1rem; }
        .remember input { width: auto; }
        footer { text-align: center; padding: 1rem; color: var(--m); font-size: .8rem; }
    </style>
    @endverbatim
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="brand">{{ trans('core-user::messages.ui.app_name') }}</div>
            @yield('content')
        </div>
    </div>
    <footer>تَوان | Tavaan Framework</footer>
</body>
</html>
