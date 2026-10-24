@extends('shop::layouts.shop')

@section('title', 'سبد خرید')

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor) . ' ریال'; ?>
    <h1 class="page-title">🛒 سبد خرید</h1>

    @if($cart->isEmpty())
        <div class="card muted">
            سبد خرید شما خالی است.
            <a href="/shop" class="btn ghost sm" style="margin-inline-start:8px">بازگشت به فروشگاه</a>
        </div>
    @else
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
            <div class="card" style="flex:2;min-width:300px;padding:0">
                <table class="data">
                    <thead>
                    <tr><th>محصول</th><th>قیمت واحد</th><th>تعداد</th><th>جمع قلم</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach($cart->items() as $item)
                        <tr>
                            <td>
                                @if($item->slug())
                                    <a href="/shop/products/{{ $item->slug() }}">{{ $item->title() }}</a>
                                @else
                                    {{ $item->title() }}
                                @endif
                            </td>
                            <td>{{ $fmt($item->unitPrice()->minor) }}</td>
                            <td>
                                <form action="/shop/cart/items/{{ $item->id() }}" method="POST" style="display:flex;gap:4px">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" name="quantity" value="{{ $item->quantity() }}" min="1" style="width:58px;padding:4px;border-radius:6px;border:1px solid var(--border);font-family:inherit">
                                    <button class="btn ghost sm" type="submit">ثبت</button>
                                </form>
                            </td>
                            <td><strong>{{ $fmt($item->lineTotal()->minor) }}</strong></td>
                            <td>
                                <form action="/shop/cart/items/{{ $item->id() }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger sm" type="submit">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card" style="flex:1;min-width:260px">
                <h2 style="font-size:1rem;margin-bottom:10px">خلاصهٔ سفارش</h2>
                <table class="data">
                    <tbody>
                    <tr><td>جمع اقلام</td><td style="text-align:left">{{ $fmt($summary['subtotal']->minor) }}</td></tr>
                    @if($summary['discount']->minor > 0)
                        <tr><td>تخفیف{{ $summary['coupon_code'] ? ' («' . $summary['coupon_code'] . '»)' : '' }}</td><td style="text-align:left;color:var(--success)">−{{ $fmt($summary['discount']->minor) }}</td></tr>
                    @endif
                    <tr><td>هزینهٔ ارسال</td><td style="text-align:left">{{ $summary['shipping']->minor === 0 ? 'رایگان' : $fmt($summary['shipping']->minor) }}</td></tr>
                    @if($summary['tax']->minor > 0)
                        <tr><td>مالیات</td><td style="text-align:left">{{ $fmt($summary['tax']->minor) }}</td></tr>
                    @endif
                    <tr><td><strong>مبلغ قابل پرداخت</strong></td><td style="text-align:left"><strong class="price">{{ $fmt($summary['total']->minor) }}</strong></td></tr>
                    </tbody>
                </table>

                <form action="/shop/cart/coupon" method="POST" style="display:flex;gap:6px;margin:12px 0">
                    @csrf
                    <input type="text" name="coupon_code" value="{{ $cart->couponCode() ?? '' }}" placeholder="کد تخفیف" style="flex:1;padding:7px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    <button class="btn ghost sm" type="submit">اعمال</button>
                </form>

                <a href="/shop/checkout" class="btn accent" style="width:100%;text-align:center">ادامهٔ فرایند خرید ←</a>
                <a href="/shop" style="display:block;text-align:center;margin-top:8px;font-size:.85rem">بازگشت به فروشگاه</a>
            </div>
        </div>
    @endif
@endsection
