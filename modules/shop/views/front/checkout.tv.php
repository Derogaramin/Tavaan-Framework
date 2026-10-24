@extends('shop::layouts.shop')

@section('title', 'تکمیل سفارش')

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor) . ' ریال'; ?>
    <h1 class="page-title">📝 تکمیل سفارش</h1>

    @guest
        <div class="card flash error" style="background:#fee2e2">
            برای ثبت سفارش ابتدا <a href="/login?redirect=/shop/checkout"><strong>وارد حساب کاربری شوید</strong></a>.
        </div>
    @else
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
            <form action="/shop/checkout" method="POST" class="card" style="flex:2;min-width:300px">
                @csrf
                <h2 style="font-size:1rem;margin-bottom:12px">آدرس ارسال</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <label>نام گیرنده *
                        <input type="text" name="receiver" required style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    </label>
                    <label>شمارهٔ تماس *
                        <input type="tel" name="phone" required placeholder="09xxxxxxxxx" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    </label>
                    <label>استان
                        <input type="text" name="province" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    </label>
                    <label>شهر *
                        <input type="text" name="city" required style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    </label>
                </div>
                <label style="display:block;margin-top:10px">آدرس کامل *
                    <textarea name="address" rows="2" required style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit"></textarea>
                </label>
                <label style="display:block;margin-top:10px">کد پستی
                    <input type="text" name="postal_code" style="width:200px;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                </label>
                <label style="display:block;margin-top:10px">یادداشت سفارش (اختیاری)
                    <textarea name="notes" rows="2" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit"></textarea>
                </label>
                <button class="btn accent" type="submit" style="margin-top:14px;width:100%">ثبت سفارش و پرداخت</button>
            </form>

            <div class="card" style="flex:1;min-width:260px">
                <h2 style="font-size:1rem;margin-bottom:10px">خلاصهٔ سبد ({{ trans_number($cart->totalQuantity()) }} قلم)</h2>
                <ul style="list-style:none;font-size:.88rem;margin-bottom:10px">
                    @foreach($cart->items() as $item)
                        <li style="display:flex;justify-content:space-between;gap:8px;border-bottom:1px dashed var(--border);padding:4px 0">
                            <span>{{ $item->title() }} × {{ trans_number($item->quantity()) }}</span>
                            <span>{{ $fmt($item->lineTotal()->minor) }}</span>
                        </li>
                    @endforeach
                </ul>
                <table class="data">
                    <tbody>
                    <tr><td>جمع اقلام</td><td style="text-align:left">{{ $fmt($summary['subtotal']->minor) }}</td></tr>
                    @if($summary['discount']->minor > 0)
                        <tr><td>تخفیف</td><td style="text-align:left;color:var(--success)">−{{ $fmt($summary['discount']->minor) }}</td></tr>
                    @endif
                    <tr><td>ارسال</td><td style="text-align:left">{{ $summary['shipping']->minor === 0 ? 'رایگان' : $fmt($summary['shipping']->minor) }}</td></tr>
                    @if($summary['tax']->minor > 0)
                        <tr><td>مالیات</td><td style="text-align:left">{{ $fmt($summary['tax']->minor) }}</td></tr>
                    @endif
                    <tr><td><strong>مبلغ قابل پرداخت</strong></td><td style="text-align:left"><strong class="price">{{ $fmt($summary['total']->minor) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endguest
@endsection
