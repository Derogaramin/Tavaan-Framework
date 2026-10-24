@extends('shop::layouts.shop')

@section('title', $success ? 'پرداخت موفق' : 'پرداخت ناموفق')

@section('content')
    <div class="card" style="max-width:520px;margin:30px auto;text-align:center">
        <div style="font-size:3rem">{{ $success ? '✅' : '❌' }}</div>
        <h1 style="font-size:1.2rem;margin:8px 0">{{ $success ? 'پرداخت با موفقیت انجام شد' : 'پرداخت تأیید نشد' }}</h1>
        @if($success)
            <p>سفارش <strong>{{ $orderNumber }}</strong> ثبت و پرداخت شد — در حال پردازش است.</p>
            @if($order !== null)
                <table class="data" style="margin:12px auto;max-width:340px">
                    <tbody>
                    <tr><td>مبلغ کل</td><td style="text-align:left">{{ trans_number($order->total()->minor) }} ریال</td></tr>
                    <tr><td>وضعیت</td><td style="text-align:left">{{ $order->status()->labelFa() }}</td></tr>
                    </tbody>
                </table>
            @endif
            <a class="btn" href="/shop/orders/{{ $orderNumber }}">مشاهدهٔ سفارش</a>
        @else
            <p class="muted">{{ $message !== '' ? $message : 'پرداخت تأیید نشد.' }} سفارش در وضعیت «در انتظار پرداخت» باقی مانده و موجودی شما رزرو است.</p>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
                @if($orderNumber !== '')
                    <a class="btn" href="/shop/pay/{{ $orderNumber }}">تلاش دوباره</a>
                @endif
                <a class="btn ghost" href="/shop/orders">سفارش‌های من</a>
            </div>
        @endif
    </div>
@endsection
