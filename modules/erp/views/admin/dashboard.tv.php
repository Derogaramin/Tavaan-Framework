@extends('erp::layouts.admin')

@section('title', 'داشبورد ERP')

@section('content')
<div class="topline">
    <h1>🏭 داشبورد ERP</h1>
    <span class="spacer"></span>
    @if($auditHealthy)
        <span class="badge green">🔐 زنجیرهٔ audit سالم</span>
    @else
        <span class="badge red">🔐 زنجیرهٔ audit آسیب‌دیده!</span>
    @endif
    @if($balanced)
        <span class="badge green">⚖ ترازنامه متوازن</span>
    @else
        <span class="badge amber">⚖ ترازنامه هنوز فعالیت ندارد</span>
    @endif
</div>

<div class="stats">
    <div class="stat"><div class="num">{{ trans_number($postedVouchers) }}</div><div class="muted">سند ثبت‌شده</div></div>
    <div class="stat"><div class="num">{{ trans_number($draftVouchers) }}</div><div class="muted">سند پیش‌نویس</div></div>
    <div class="stat"><div class="num">{{ trans_number($pendingPos) }}</div><div class="muted">خرید در انتظار تأیید</div></div>
    <div class="stat"><div class="num">{{ trans_number($openSalesOrders) }}</div><div class="muted">سفارش فروش قطعی</div></div>
    <div class="stat"><div class="num">{{ trans_number($lowStockCount) }}</div><div class="muted">کالای زیر نقطهٔ سفارش</div></div>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">⚖ وضعیت مالی (معادلهٔ حسابداری)</h2>
    <table class="data">
        <tr><th>دارایی‌ها</th><td class="num">{{ trans_number($assets) }} ریال</td></tr>
        <tr><th>بدهی‌ها</th><td class="num">{{ trans_number($liabilities) }} ریال</td></tr>
        <tr><th>حقوق صاحبان سهام + سود دوره</th><td class="num">{{ trans_number($equity) }} ریال</td></tr>
        <tr>
            <th>تراز</th>
            <td>
                @if($balanced)<span class="badge green">A = L + E ✓</span>@else<span class="badge amber">—</span>@endif
            </td>
        </tr>
    </table>
    <p class="muted" style="margin-top:8px">
        همهٔ اسناد از دفتر کل مرکزی خوانده می‌شوند — قانون طلایی: در هر سند
        SUM(debit) = SUM(credit) (AC-ERP-09).
    </p>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">⚡ دسترسی سریع</h2>
    <a class="btn sm" href="/admin/erp/vouchers/create">🧾 سند دستی جدید</a>
    <a class="btn sm ghost" href="/admin/erp/purchases/create">🛒 سفارش خرید</a>
    <a class="btn sm ghost" href="/admin/erp/sales/create">💼 سفارش فروش</a>
    <a class="btn sm ghost" href="/admin/erp/inventory">📦 رسید/حوالهٔ انبار</a>
    <a class="btn sm ghost" href="/admin/erp/reports">📈 گزارش‌های مالی</a>
    <a class="btn sm ghost" href="/admin/erp/audit">🔐 ردپای audit</a>
</div>
@endsection
