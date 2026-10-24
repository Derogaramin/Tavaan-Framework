@extends('shop::layouts.shop')

@section('title', 'درگاه آزمایشی پرداخت')

@section('content')
    <div class="card" style="max-width:520px;margin:30px auto;text-align:center">
        <div style="font-size:3rem">🧪</div>
        <h1 style="font-size:1.2rem;margin:8px 0">درگاه آزمایشی پرداخت</h1>
        <p class="muted">این صفحه شبیه‌ساز درگاه پرداخت است (محیط توسعه/دمو).</p>
        @if($order !== null)
            <table class="data" style="margin:14px auto;max-width:340px">
                <tbody>
                <tr><td>شمارهٔ سفارش</td><td style="text-align:left"><strong>{{ $order->orderNumber() }}</strong></td></tr>
                <tr><td>مبلغ</td><td style="text-align:left"><strong>{{ trans_number($order->total()->minor) }} ریال</strong></td></tr>
                <tr><td>authority</td><td style="text-align:left;font-size:.75rem;word-break:break-all">{{ $authority }}</td></tr>
                </tbody>
            </table>
        @endif
        <div style="display:flex;gap:10px;justify-content:center;margin-top:14px">
            <a class="btn" href="/shop/pay/callback?authority={{ urlencode($authority) }}&status=ok">✔ پرداخت موفق</a>
            <a class="btn danger" href="/shop/pay/callback?authority={{ urlencode($authority) }}&status=fail">✖ پرداخت ناموفق</a>
        </div>
    </div>
@endsection
