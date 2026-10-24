@extends('shop::layouts.admin')

@section('title', 'سفارش ' . $order->orderNumber())

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor); ?>
    <div class="topline">
        <h1>🧾 سفارش {{ $order->orderNumber() }}</h1>
        <span class="badge {{ $order->status()->value === 'paid' ? 'green' : '' }}" style="align-self:center;font-size:.85rem;padding:4px 12px">{{ $order->status()->labelFa() }}</span>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:8px">اقلام ({{ trans_number($order->itemCount()) }} عدد)</h2>
        <table class="data">
            <thead><tr><th>قلم</th><th>SKU</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
            <tbody>
            @foreach($order->items() as $item)
                <tr>
                    <td>{{ $item->title }}</td>
                    <td class="muted">{{ $item->sku ?? '—' }}</td>
                    <td>{{ trans_number($item->quantity) }}</td>
                    <td>{{ $fmt($item->unitPrice()->minor) }}</td>
                    <td><strong>{{ $fmt($item->lineTotal()->minor) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <table class="data" style="max-width:360px;margin:12px 0 0 auto">
            <tbody>
            <tr><td>جمع اقلام</td><td style="text-align:left">{{ $fmt($order->subtotal()->minor) }}</td></tr>
            @if($order->discount()->minor > 0)
                <tr><td>تخفیف</td><td style="text-align:left;color:var(--success)">−{{ $fmt($order->discount()->minor) }}</td></tr>
            @endif
            <tr><td>ارسال</td><td style="text-align:left">{{ $fmt($order->shippingCost()->minor) }}</td></tr>
            <tr><td>مالیات</td><td style="text-align:left">{{ $fmt($order->tax()->minor) }}</td></tr>
            <tr><td><strong>کل</strong></td><td style="text-align:left"><strong>{{ $fmt($order->total()->minor) }} ریال</strong></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:8px">اطلاعات</h2>
        <p class="muted">مشتری: {{ trans_number($order->customerId()) }} • روش پرداخت: {{ $order->paymentMethod() ?? '—' }} • authority: <span style="font-size:.75rem">{{ $order->paymentAuthority() ?? '—' }}</span></p>
        @if($order->shippingAddress() !== null)
            <?php $addr = $order->shippingAddress(); ?>
            <p style="margin-top:6px"><strong>آدرس:</strong> {{ $addr->receiver }} — {{ $addr->phone }} — {{ $addr->province }} {{ $addr->city }}، {{ $addr->address }} {{ $addr->postalCode }}</p>
        @endif
        @if($order->notes())
            <p class="muted" style="margin-top:6px">یادداشت: {{ $order->notes() }}</p>
        @endif
        <p class="muted" style="margin-top:6px">
            ثبت: {{ $order->placedAt() !== null ? jalali($order->placedAt(), 'Y/m/d H:i') : '—' }}
            • پرداخت: {{ $order->paidAt() !== null ? jalali($order->paidAt(), 'Y/m/d H:i') : '—' }}
            • ارسال: {{ $order->shippedAt() !== null ? jalali($order->shippedAt(), 'Y/m/d H:i') : '—' }}
        </p>
    </div>

    <div class="card">
        <h2 style="font-size:1rem;margin-bottom:10px">عملیات</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if($order->status()->value === 'paid')
                <form action="/admin/shop/orders/{{ $order->id() }}/status" method="POST" style="display:flex;gap:6px">
                    @csrf
                    <input type="hidden" name="action" value="ship">
                    <input type="text" name="tracking_code" placeholder="کد رهگیری پستی" style="max-width:190px">
                    <button class="btn sm" type="submit">📦 ارسال</button>
                </form>
            @endif
            @if($order->status()->value === 'shipped')
                <form action="/admin/shop/orders/{{ $order->id() }}/status" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="deliver">
                    <button class="btn sm" type="submit">✔ تحویل شد</button>
                </form>
            @endif
            @if(in_array($order->status()->value, ['pending', 'paid'], true))
                <form action="/admin/shop/orders/{{ $order->id() }}/status" method="POST" style="display:flex;gap:6px">
                    @csrf
                    <input type="hidden" name="action" value="cancel">
                    <input type="text" name="reason" placeholder="دلیل لغو" style="max-width:190px">
                    <button class="btn danger sm" type="submit" onclick="return confirm('لغو + بازگشت موجودی؟')">✖ لغو</button>
                </form>
            @endif
            @if($order->isPaid() && in_array($order->status()->value, ['paid', 'shipped', 'delivered'], true))
                <form action="/admin/shop/orders/{{ $order->id() }}/refund" method="POST" style="display:flex;gap:6px">
                    @csrf
                    <input type="text" name="reason" placeholder="دلیل استرداد" style="max-width:190px">
                    <button class="btn danger sm" type="submit" onclick="return confirm('استرداد وجه؟')">💸 استرداد</button>
                </form>
            @endif
            <form action="/admin/shop/orders/{{ $order->id() }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button class="btn danger sm" type="submit" onclick="return confirm('حذف کامل سفارش؟')">حذف</button>
            </form>
        </div>
    </div>

    @if(count($movements) > 0)
        <div class="card">
            <h2 style="font-size:1rem;margin-bottom:8px">حرکت‌های موجودی این سفارش</h2>
            <table class="data">
                <thead><tr><th>محصول</th><th>تنوع</th><th>تغییر</th><th>موجودی پس از آن</th><th>دلیل</th><th>زمان</th></tr></thead>
                <tbody>
                @foreach($movements as $m)
                    <tr>
                        <td class="muted" style="font-size:.75rem">{{ substr((string) $m['product_id'], 0, 10) }}…</td>
                        <td class="muted">{{ $m['variant_id'] ? substr((string) $m['variant_id'], 0, 8) . '…' : '—' }}</td>
                        <td style="color:{{ (int) $m['delta'] < 0 ? 'var(--danger)' : 'var(--success)' }}">{{ trans_number((int) $m['delta']) }}</td>
                        <td>{{ trans_number((int) $m['stock_after']) }}</td>
                        <td>{{ $m['reason'] }}</td>
                        <td class="muted">{{ $m['created_at'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
