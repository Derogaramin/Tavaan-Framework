<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'مدیریت آموزش') — {{ config('app.name') }}</title>
    @verbatim
    <style>
        :root { --primary: #1d4ed8; --primary-dark: #1e3a8a; --bg: #f1f5f9; --card: #fff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --danger: #dc2626; --success: #16a34a; --accent: #f59e0b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; background: var(--bg); color: var(--text); line-height: 1.7; }
        a { color: var(--primary-dark); text-decoration: none; }
        .shell { display: flex; min-height: 100vh; }
        aside.side { flex: 0 0 210px; background: #0f172a; color: #e2e8f0; padding: 16px 12px; }
        aside.side .logo { font-weight: 800; font-size: 1.05rem; margin-bottom: 18px; color: #93c5fd; }
        aside.side a { display: block; color: #cbd5e1; padding: 8px 10px; border-radius: 8px; margin-bottom: 3px; font-size: .9rem; }
        aside.side a:hover, aside.side a.active { background: #1e293b; color: #fff; }
        main.admin { flex: 1; padding: 20px 24px; }
        .topline { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .topline h1 { font-size: 1.2rem; }
        .topline .spacer { flex: 1; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; font-size: .88rem; }
        table.data th, table.data td { border-bottom: 1px solid var(--border); padding: 8px; text-align: right; }
        table.data th { color: var(--muted); }
        .btn { display: inline-block; border: 0; cursor: pointer; background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 9px; font-family: inherit; font-size: .88rem; font-weight: 700; }
        .btn.ghost { background: transparent; color: var(--primary-dark); border: 1px solid var(--primary); }
        .btn.danger { background: var(--danger); }
        .btn.success { background: var(--success); }
        .btn.sm { padding: 3px 10px; font-size: .78rem; }
        .badge { display: inline-block; font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; }
        .badge.green { background: #dcfce7; color: #14532d; }
        .badge.red { background: #fee2e2; color: #991b1b; }
        .badge.amber { background: #fef3c7; color: #92400e; }
        .badge.blue { background: #dbeafe; color: #1e40af; }
        .muted { color: var(--muted); font-size: .82rem; }
        .flash { padding: 9px 13px; border-radius: 9px; margin-bottom: 12px; font-size: .88rem; }
        .flash.success { background: #dcfce7; color: #14532d; }
        .flash.error { background: #fee2e2; color: #7f1d1d; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid label { display: flex; flex-direction: column; gap: 4px; font-size: .85rem; }
        .form-grid .full { grid-column: 1 / -1; }
        input[type=text], input[type=number], input[type=email], input[type=url], select, textarea { padding: 8px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .9rem; width: 100%; }
        textarea { min-height: 110px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .stat { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px; text-align: center; }
        .stat .num { font-size: 1.3rem; font-weight: 800; color: var(--primary-dark); }
        .pagination { display: flex; gap: 6px; margin-top: 14px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 4px 11px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); font-size: .82rem; }
        .pagination span.current { background: var(--primary); color: #fff; border-color: var(--primary); }
        form.inline { display: inline; }
        .progress-track { background: #e2e8f0; border-radius: 999px; height: .45rem; overflow: hidden; min-width: 90px; }
        .progress-fill { background: linear-gradient(90deg, var(--primary), #60a5fa); height: 100%; }
        .section-box { border: 1px dashed var(--border); border-radius: 10px; padding: 12px; margin-bottom: 12px; }
        .lesson-line { display: flex; gap: 8px; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: .87rem; flex-wrap: wrap; }
        .lesson-line .grow { flex: 1; }
    </style>
    @endverbatim
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="logo">🎓 مدیریت آموزش</div>
        <a href="/admin/lms/courses">📚 دوره‌ها</a>
        <a href="/admin/lms/grading">📝 نمره‌دهی</a>
        <a href="/admin/lms/enrollments">🧾 ثبت‌نام‌ها</a>
        <a href="/admin/lms/certificates">🏅 گواهی‌ها</a>
        <a href="/admin/lms/reports/progress">📊 گزارش پیشرفت</a>
        <a href="/admin/lms/reports/progress.csv">⬇ گزارش CSV</a>
        <a href="/admin">← بازگشت به پنل</a>
        <a href="/lms">🎓 ویترین آکادمی</a>
    </aside>
    <main class="admin">
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
    </main>
</div>
</body>
</html>
