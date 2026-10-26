<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'فرایندهای من') — {{ config('app.name') }}</title>
    @verbatim
    <style>
        :root { --primary: #0e7490; --primary-dark: #164e63; --bg: #f0f9ff; --card: #fff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --danger: #dc2626; --success: #16a34a; --accent: #f59e0b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; }
        a { color: var(--primary-dark); text-decoration: none; }
        header.top { background: #082f49; color: #e0f2fe; padding: 12px 20px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        header.top .logo { font-weight: 800; color: #67e8f9; }
        header.top a { color: #bae6fd; font-size: .9rem; }
        header.top a:hover { color: #fff; }
        main.wrap { max-width: 960px; margin: 20px auto; padding: 0 16px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .card h1 { font-size: 1.15rem; margin-bottom: 10px; }
        .card h2 { font-size: 1rem; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; font-size: .88rem; }
        table.data th, table.data td { border-bottom: 1px solid var(--border); padding: 8px; text-align: right; vertical-align: top; }
        table.data th { color: var(--muted); }
        .btn { display: inline-block; border: 0; cursor: pointer; background: var(--primary); color: #fff; padding: 8px 18px; border-radius: 9px; font-family: inherit; font-size: .9rem; font-weight: 700; }
        .btn.ghost { background: transparent; color: var(--primary-dark); border: 1px solid var(--primary); }
        .btn.danger { background: var(--danger); }
        .btn.success { background: var(--success); }
        .btn.sm { padding: 3px 10px; font-size: .78rem; }
        .badge { display: inline-block; font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; }
        .badge.green { background: #dcfce7; color: #14532d; }
        .badge.red { background: #fee2e2; color: #991b1b; }
        .badge.amber { background: #fef3c7; color: #92400e; }
        .badge.blue { background: #e0f2fe; color: #075985; }
        .muted { color: var(--muted); font-size: .82rem; }
        .flash { padding: 9px 13px; border-radius: 9px; margin-bottom: 12px; font-size: .88rem; }
        .flash.success { background: #dcfce7; color: #14532d; }
        .flash.error { background: #fee2e2; color: #7f1d1d; }
        .form-row { margin-bottom: 12px; }
        .form-row label { display: flex; flex-direction: column; gap: 4px; font-size: .85rem; }
        input[type=text], input[type=number], input[type=email], input[type=tel], input[type=date], select, textarea { padding: 8px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .9rem; width: 100%; }
        textarea { min-height: 90px; }
        textarea.code { direction: ltr; text-align: left; font-family: ui-monospace, monospace; font-size: .8rem; }
        form.inline { display: inline; }
        .timeline { border-inline-start: 2px solid var(--border); padding-inline-start: 14px; }
        .timeline .ev { position: relative; padding-block: 8px; }
        .timeline .ev::before { content: ''; position: absolute; inset-inline-start: -20px; top: 15px; width: 10px; height: 10px; border-radius: 50%; background: var(--primary); }
        .diagram-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px; direction: ltr; }
        .help { font-size: .78rem; color: var(--muted); }
        .req { color: var(--danger); }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 720px) { .grid2 { grid-template-columns: 1fr; } }
    </style>
    @endverbatim
</head>
<body>
<header class="top">
    <span class="logo">🔄 فرایندهای من</span>
    <a href="/bpms/tasks">☑️ کارهای من</a>
    <a href="/bpms/processes">🚀 آغاز فرایند</a>
    <a href="/bpms/instances">🚦 نمونه‌های من</a>
</header>
<main class="wrap">
    @if(($flash = session('status')) !== null)
        <div class="flash success">{{ is_string($flash) ? trans('bpms::flash.' . $flash) : '' }}</div>
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
</main>
</body>
</html>
