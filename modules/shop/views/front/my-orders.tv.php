@extends('shop::layouts.shop')

@section('title', 'سفارش‌های من')

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor) . ' ریال'; ?>
    <h1 class="page-title">🧾 سفارش‌های من ({{ trans_number($total) }})</h1>

    @if(count($orders) === 0)
        <div class="card muted">
            هنوز سفارشی ثبت نکرده‌اید.
            <a href="/shop" class="btn ghost sm">شروع خرید</a>
        </div>
    @else
        <div class="card" style="padding:0">
            <table class="data">
                <thead>
                <tr><th>شمارهٔ سفارش</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><a href="/shop/orders/{{ $order->orderNumber() }}"><strong>{{ $order->orderNumber() }}</strong></a></td>
                        <td>{{ $order->placedAt() !== null ? jalali($order->placedAt(), 'Y/m/d H:i') : '—' }}</td>
                        <td><span class="badge" style="{{ $order->status()->value === 'paid' || $order->status()->value === 'delivered' ? 'background:#dcfce7;color:#14532d' : ($order->status()->value === 'canceled' || $order->status()->value === 'refunded' ? 'background:#fee2e2;color:#991b1b' : '') }}">{{ $order->status()->labelFa() }}</span></td>
                        <td>{{ $fmt($order->total()->minor) }}</td>
                        <td>
                            <a class="btn ghost sm" href="/shop/orders/{{ $order->orderNumber() }}">جزئیات</a>
                            @if($order->isCancelableByCustomer())
                                <form action="/shop/orders/{{ $order->orderNumber() }}/cancel" method="POST" class="inline">
                                    @csrf
                                    <button class="btn danger sm" type="submit" onclick="return confirm('سفارش لغو شود؟')">لغو</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if($total > $perPage)
            <?php $pages = (int) ceil($total / $perPage); ?>
            <div class="pagination">
                @for($p = 1; $p <= $pages; $p++)
                    @if($p === $page)
                        <span class="current">{{ trans_number($p) }}</span>
                    @else
                        <a href="?page={{ $p }}">{{ trans_number($p) }}</a>
                    @endif
                @endfor
            </div>
        @endif
    @endif
@endsection
