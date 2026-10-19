<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — پیشخوان {{ $appTitle ?? 'تَوان' }}</title>
    {{-- RTL کامل با فونت فارسی (Vazirmatn در صورت نصب محلی؛ در غیر این صورت fallback — DOC-078 §8) --}}
    <style>
        :root {
            --bg: #0f172a; --panel: #1e293b; --border: #334155;
            --text: #e2e8f0; --muted: #94a3b8; --accent: #f59e0b; --danger: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font-family: Vazirmatn, "Segoe UI", Tahoma, "Iranian Sans", sans-serif;
            font-size: 14px; line-height: 1.8;
        }
        a { color: var(--accent); text-decoration: none; }
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 230px; background: var(--panel); border-inline-start: 1px solid var(--border);
            padding: 16px 12px; flex-shrink: 0;
        }
        .admin-sidebar .brand { font-size: 18px; font-weight: 700; padding: 6px 10px 16px; color: var(--accent); }
        .admin-sidebar nav a {
            display: block; padding: 8px 10px; border-radius: 8px; color: var(--text); margin-bottom: 2px;
        }
        .admin-sidebar nav a:hover { background: rgba(245, 158, 11, .12); }
        .admin-main { flex: 1; padding: 24px 28px; min-width: 0; }
        .admin-card {
            background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
            padding: 18px; margin-bottom: 18px;
        }
        .admin-title { font-size: 20px; font-weight: 700; margin: 0 0 14px; }
        table.admin-table { width: 100%; border-collapse: collapse; }
        table.admin-table th, table.admin-table td {
            padding: 8px 10px; border-bottom: 1px solid var(--border); text-align: right;
        }
        table.admin-table th { color: var(--muted); font-weight: 500; font-size: 12.5px; }
        .btn {
            display: inline-block; padding: 7px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--panel); color: var(--text); cursor: pointer; font-family: inherit; font-size: 13px;
        }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #1f2937; font-weight: 700; }
        .btn.danger { background: var(--danger); border-color: var(--danger); color: #fff; }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 4px; color: var(--muted); }
        .field input, .field select, .field textarea {
            width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border);
            background: #0b1220; color: var(--text); font-family: inherit; font-size: 13.5px;
        }
        .error-text { color: var(--danger); font-size: 12px; }
        .muted { color: var(--muted); }
        .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
        .toolbar input[type=search], .toolbar select {
            padding: 7px 10px; border-radius: 8px; border: 1px solid var(--border);
            background: #0b1220; color: var(--text); font-family: inherit;
        }
        .pager { display: flex; gap: 6px; margin-top: 14px; }
        .pager a, .pager span {
            padding: 5px 11px; border: 1px solid var(--border); border-radius: 8px; color: var(--text);
        }
        .pager .current { background: var(--accent); color: #1f2937; font-weight: 700; border-color: var(--accent); }
        .widget-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        .flash-error { background: rgba(239, 68, 68, .12); border: 1px solid var(--danger); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="brand">⚡ {{ $appTitle ?? 'تَوان' }}</div>
        <nav>
            @foreach($menu ?? [] as $item)
                <a href="{{ $item['url'] }}">{{ $item['icon'] }} {{ $item['label'] }}</a>
            @endforeach
        </nav>
    </aside>
    <main class="admin-main">
        @yield('content')
    </main>
</div>
</body>
</html>
