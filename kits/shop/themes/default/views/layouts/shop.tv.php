<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('seo_meta')
    <title>@yield('title', 'فروشگاه') — {{ config('app.name') }}</title>
    <style>
        :root {
            --primary: #0d9488; --primary-dark: #0f766e; --accent: #f59e0b;
            --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --muted: #64748b;
            --border: #e2e8f0; --danger: #dc2626; --success: #16a34a; --radius: 12px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; background: var(--bg); color: var(--text); line-height: 1.8; }
        a { color: var(--primary-dark); text-decoration: none; }
        .container { max-width: 1160px; margin: 0 auto; padding: 0 16px; }
        header.site { background: var(--card); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 20; }
        .topbar { display: flex; align-items: center; gap: 14px; padding: 10px 0; flex-wrap: wrap; }
        .brand { font-size: 1.2rem; font-weight: 800; color: var(--primary-dark); }
        nav.main { display: flex; gap: 12px; flex-wrap: wrap; font-size: .9rem; }
        nav.main a { color: var(--text); padding: 4px 8px; border-radius: 8px; }
        nav.main a:hover { background: var(--bg); }
        .header-tools { margin-inline-start: auto; display: flex; align-items: center; gap: 10px; }
        .searchbox { display: flex; }
        .searchbox input { border: 1px solid var(--border); border-radius: 8px 0 0 8px; padding: 6px 10px; font-family: inherit; width: 170px; }
        .searchbox button { border: 0; background: var(--primary); color: #fff; border-radius: 0 8px 8px 0; padding: 6px 12px; cursor: pointer; }
        .cart-badge { background: var(--accent); color: #1c1917; border-radius: 999px; padding: 4px 12px; font-size: .82rem; font-weight: 700; }
        .btn { display: inline-block; border: 0; cursor: pointer; background: var(--primary); color: #fff; padding: 9px 18px; border-radius: 10px; font-family: inherit; font-size: .92rem; font-weight: 700; }
        .btn:hover { background: var(--primary-dark); }
        .btn.ghost { background: transparent; color: var(--primary-dark); border: 1px solid var(--primary); }
        .btn.danger { background: var(--danger); }
        .btn.accent { background: var(--accent); color: #1c1917; }
        .btn.sm { padding: 4px 12px; font-size: .8rem; }
        .btn:disabled { background: var(--muted); cursor: not-allowed; }
        main { padding: 22px 0 48px; }
        h1.page-title { font-size: 1.3rem; margin-bottom: 14px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-bottom: 16px; }
        .flash { padding: 10px 14px; border-radius: 10px; margin-bottom: 12px; font-size: .9rem; }
        .flash.success { background: #dcfce7; color: #14532d; }
        .flash.error { background: #fee2e2; color: #7f1d1d; }
        .flash.info { background: #e0f2fe; color: #0c4a6e; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; }
        .product-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; display: flex; flex-direction: column; }
        .product-card .thumb { height: 130px; background: linear-gradient(135deg, #ccfbf1, #f0fdfa); display: flex; align-items: center; justify-content: center; font-size: 2.4rem; }
        .product-card .body { padding: 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .product-card h3 { font-size: .95rem; line-height: 1.5; }
        .price-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .price { font-weight: 800; color: var(--primary-dark); }
        .old-price { text-decoration: line-through; color: var(--muted); font-size: .8rem; }
        .badge { display: inline-block; font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; }
        .badge.sale { background: #fee2e2; color: #991b1b; }
        .badge.oos { background: #e2e8f0; color: #475569; }
        .muted { color: var(--muted); font-size: .85rem; }
        table.data { width: 100%; border-collapse: collapse; font-size: .9rem; }
        table.data th, table.data td { border-bottom: 1px solid var(--border); padding: 8px; text-align: right; }
        table.data th { color: var(--muted); font-weight: 600; }
        form.inline { display: inline; }
        .pagination { display: flex; gap: 6px; margin-top: 18px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 5px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); font-size: .85rem; }
        .pagination span.current { background: var(--primary); color: #fff; border-color: var(--primary); }
        footer.site { border-top: 1px solid var(--border); background: var(--card); padding: 16px 0; font-size: .85rem; color: var(--muted); text-align: center; }
        .announce { background: linear-gradient(90deg, #0f766e, #0d9488); color: #f0fdfa; text-align: center; font-size: .82rem; padding: 6px 10px; }
        @verbatim
        @media (max-width: 720px) {
            .header-tools { width: 100%; margin-inline-start: 0; }
            .searchbox input { width: 100%; }
        }
        @endverbatim
    </style>
</head>
<body>
<div class="announce">{{ config('shop.theme.announce', 'ارسال رایگان برای سفارش‌های بالای ۵۰٬۰۰۰٬۰۰۰ ریال 🎉') }}</div>
<header class="site">
    <div class="container topbar">
        <a class="brand" href="/shop">🛒 {{ config('shop.theme.store_name', config('app.name', 'فروشگاه تَوان')) }}</a>
        <nav class="main">
            <a href="/shop">محصولات</a>
            <a href="/shop/cart">سبد خرید</a>
            @auth
                <a href="/shop/orders">سفارش‌های من</a>
            @endauth
        </nav>
        <div class="header-tools">
            <form class="searchbox" action="/shop/search" method="GET">
                <input type="search" name="q" value="{{ $query ?? '' }}" placeholder="جست‌وجوی محصول…">
                <button type="submit">جست‌وجو</button>
            </form>
            @if(($cart ?? null) !== null && $cart->count() > 0)
                <a class="cart-badge" href="/shop/cart">🛍 {{ trans_number($cart->totalQuantity()) }} قلم</a>
            @else
                <a class="cart-badge" href="/shop/cart">🛍 خالی</a>
            @endif
            @auth
                <a class="btn ghost sm" href="/admin">پنل</a>
            @else
                <a class="btn ghost sm" href="/login">ورود</a>
            @endauth
        </div>
    </div>
</header>
<main>
    <div class="container">
        @if(($flash = session('status')) !== null)
            <div class="flash success">{{ trans('shop::flash.' . $flash) }}</div>
        @endif
        @if(($error = session('error')) !== null)
            <div class="flash error">{{ is_string($error) ? $error : '' }}</div>
        @endif
        @if(($errors = session('errors')) !== null && is_array($errors))
            <div class="flash error">
                <ul style="margin-inline-start:18px">
                    @foreach($errors as $fieldErrors)
                        @foreach((array) $fieldErrors as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </div>
</main>
<footer class="site">
    <div class="container">
        {{ config('shop.theme.store_name', 'فروشگاه تَوان') }} — قدرت‌گرفته از تَوان {{ config('app.version', '0.10') }} • کیت shop (DOC-093)
    </div>
</footer>
</body>
</html>
