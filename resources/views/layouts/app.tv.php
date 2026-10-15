<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'تَوان | Tavaan')</title>
    <style>
        :root { color-scheme: dark; }
        body { font-family: Tahoma, Vazirmatn, sans-serif; background: #0b1120; color: #e2e8f0; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
        header { padding: 16px 32px; border-bottom: 1px solid #1f2937; display: flex; justify-content: space-between; align-items: center; }
        main { flex: 1; padding: 32px; max-width: 960px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        footer { padding: 12px 32px; border-top: 1px solid #1f2937; color: #64748b; font-size: 12px; text-align: center; }
        a { color: #38bdf8; text-decoration: none; }
        .logo { font-weight: 800; font-size: 18px; color: #f59e0b; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 20px 24px; margin-bottom: 16px; }
        code { background: #0b1120; padding: 2px 8px; border-radius: 6px; direction: ltr; display: inline-block; }
        @stack('styles')
    </style>
</head>
<body>
    <header>
        <span class="logo">⚡ تَوان</span>
        <nav><a href="{{ route('home') }}">خانه</a> • <a href="{{ route('time') }}">ساعت</a> • <a href="{{ route('demo.counter') }}">شمارنده (session)</a></nav>
    </header>
    <main>
        @yield('content')
    </main>
    <footer>
        تَوان {{ TVN_VERSION }} — فاز M2 (وب) • {{ jalali(null, 'Y/m/d') }} • @yield('footer-extra', 'صفر وابستگی خارجی')
    </footer>
    @stack('scripts')
</body>
</html>
