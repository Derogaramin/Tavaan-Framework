@extends('shop::layouts.shop')

@section('title', 'سفارش ' . $order->orderNumber())

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor) . ' ریال'; ?>
    <h1 class="page-title">سفارش {{ $order->orderNumber() }}</h1>

    <div class="card">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
            <span class="badge" style="font-size:.85rem;padding:4px 12px">{{ $order->status()->labelFa() }}</span>
            <span class="muted">ثبت: {{ $order->placedAt() !== null ? jalali($order->placedAt(), 'Y/m/d H:i') : '—' }}</span>
            @if($order->paidAt() !== null)
                <span class="muted">پرداخت: {{ jalali($order->paidAt(), 'Y/m/d H:i') }}</span>
            @endif
            @if($order->trackingCode())
                <span class="muted">کد رهگیری ارسال: {{ $order->trackingCode() }}</span>
            @endif
        </div>

        <table class="data">
            <thead><tr><th>قلم</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
            <tbody>
            @foreach($order->items() as $item)
                <tr>
                    <td>{{ $item->title }}@if($item->sku) <span class="muted">({{ $item->sku }})</span> @endif</td>
                    <td>{{ trans_number($item->quantity) }}</td>
                    <td>{{ $fmt($item->unitPrice()->minor) }}</td>
                    <td><strong>{{ $fmt($item->lineTotal()->minor) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="data" style="margin-top:12px;max-width:380px;margin-inline-start:auto">
            <tbody>
            <tr><td>جمع اقلام</td><td style="text-align:left">{{ $fmt($order->subtotal()->minor) }}</td></tr>
            @if($order->discount()->minor > 0)
                <tr><td>تخفیف</td><td style="text-align:left;color:var(--success)">−{{ $fmt($order->discount()->minor) }}</td></tr>
            @endif
            <tr><td>ارسال</td><td style="text-align:left">{{ $order->shippingCost()->minor === 0 ? 'رایگان' : $fmt($order->shippingCost()->minor) }}</td></tr>
            @if($order->tax()->minor > 0)
                <tr><td>مالیات</td><td style="text-align:left">{{ $fmt($order->tax()->minor) }}</td></tr>
            @endif
            <tr><td><strong>مبلغ کل</strong></td><td style="text-align:left"><strong class="price">{{ $fmt($order->total()->minor) }}</strong></td></tr>
            </tbody>
        </table>

        @if($order->shippingAddress() !== null)
            <?php $addr = $order->shippingAddress(); ?>
            <div style="margin-top:12px;font-size:.9rem">
                <strong>آدرس ارسال:</strong>
                {{ $addr->receiver }} — {{ $addr->phone }}
                <br>{{ $addr->province }} {{ $addr->city }}، {{ $addr->address }}
                @if($addr->postalCode !== '') <span class="muted">({{ $addr->postalCode }})</span> @endif
            </div>
        @endif

        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
            @if($order->status()->value === 'pending')
                <a class="btn accent" href="/shop/pay/{{ $order->orderNumber() }}">پرداخت سفارش</a>
            @endif
            @if($canCancel)
                <form action="/shop/orders/{{ $order->orderNumber() }}/cancel" method="POST" class="inline">
                    @csrf
                    <button class="btn danger" type="submit" onclick="return confirm('سفارش لغو شود؟ موجودی بازمی‌گردد.')">لغو سفارش</button>
                </form>
            @endif
            <a class="btn ghost" href="/shop/orders">بازگشت</a>
        </div>
    </div>
@endsection
