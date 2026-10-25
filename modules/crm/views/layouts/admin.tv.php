<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'مدیریت CRM') — {{ config('app.name') }}</title>
    @verbatim
    <style>
        :root { --primary: #0284c7; --primary-dark: #075985; --bg: #f1f5f9; --card: #fff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --danger: #dc2626; --success: #16a34a; --accent: #f59e0b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; }
        a { color: var(--primary-dark); text-decoration: none; }
        .shell { display: flex; min-height: 100vh; }
        aside.side { flex: 0 0 210px; background: #0c1a2b; color: #e2e8f0; padding: 16px 12px; }
        aside.side .logo { font-weight: 800; font-size: 1.05rem; margin-bottom: 18px; color: #7dd3fc; }
        aside.side a { display: block; color: #cbd5e1; padding: 8px 10px; border-radius: 8px; margin-bottom: 3px; font-size: .9rem; }
        aside.side a:hover, aside.side a.active { background: #16324a; color: #fff; }
        main.admin { flex: 1; padding: 20px 24px; }
        .topline { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .topline h1 { font-size: 1.2rem; }
        .topline .spacer { flex: 1; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; font-size: .88rem; }
        table.data th, table.data td { border-bottom: 1px solid var(--border); padding: 8px; text-align: right; vertical-align: top; }
        table.data th { color: var(--muted); }
        .btn { display: inline-block; border: 0; cursor: pointer; background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 9px; font-family: inherit; font-size: .88rem; font-weight: 700; }
        .btn.ghost { background: transparent; color: var(--primary-dark); border: 1px solid var(--primary); }
        .btn.danger { background: var(--danger); }
        .btn.success { background: var(--success); }
        .btn.warn { background: var(--accent); }
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
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid label { display: flex; flex-direction: column; gap: 4px; font-size: .85rem; }
        .form-grid .full { grid-column: 1 / -1; }
        input[type=text], input[type=number], input[type=email], input[type=url], input[type=date], input[type=datetime-local], select, textarea { padding: 8px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .9rem; width: 100%; }
        textarea { min-height: 100px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .stat { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px; text-align: center; }
        .stat .num { font-size: 1.3rem; font-weight: 800; color: var(--primary-dark); }
        .pagination { display: flex; gap: 6px; margin-top: 14px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 4px 11px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); font-size: .82rem; }
        .pagination span.current { background: var(--primary); color: #fff; border-color: var(--primary); }
        form.inline { display: inline; }
        .board { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
        .board .col { background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 10px; }
        .board .col h3 { font-size: .82rem; color: var(--muted); margin-bottom: 8px; }
        .board .item { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 8px; margin-bottom: 8px; font-size: .82rem; }
        .progress-track { background: #e2e8f0; border-radius: 999px; height: .45rem; overflow: hidden; min-width: 70px; }
        .progress-fill { background: linear-gradient(90deg, var(--primary), #7dd3fc); height: 100%; }
        .score-pill { display: inline-block; min-width: 2.2rem; text-align: center; border-radius: 999px; font-weight: 800; font-size: .8rem; padding: 1px 8px; }
        .score-hot { background: #fee2e2; color: #991b1b; }
        .score-warm { background: #fef3c7; color: #92400e; }
        .score-cold { background: #e0f2fe; color: #075985; }
        .timeline { border-inline-start: 2px solid var(--border); padding-inline-start: 14px; }
        .timeline .ev { position: relative; padding-block: 8px; }
        .timeline .ev::before { content: ''; position: absolute; inset-inline-start: -20px; top: 15px; width: 10px; height: 10px; border-radius: 50%; background: var(--primary); }
    </style>
    @endverbatim
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="logo">🤝 مدیریت CRM</div>
        <a href="/admin/crm">🏠 داشبورد</a>
        <a href="/admin/crm/contacts">👥 مخاطبان</a>
        <a href="/admin/crm/leads">🎯 سرنخ‌ها</a>
        <a href="/admin/crm/opportunities">📈 فرصت‌ها</a>
        <a href="/admin/crm/tickets">🎫 تیکت‌ها</a>
        <a href="/admin/crm/activities">☑️ فعالیت‌ها</a>
        <a href="/admin/crm/campaigns">📣 کمپین‌ها</a>
        <a href="/admin/crm/reports">📊 گزارش‌ها</a>
        <a href="/admin/crm/reports/pipeline.csv">⬇ CSV</a>
        <a href="/admin">← بازگشت به پنل</a>
        <a href="/crm/support">🎧 پرتال پشتیبانی</a>
    </aside>
    <main class="admin">
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
        @yield('content')
    </main>
</div>
</body>
</html>
