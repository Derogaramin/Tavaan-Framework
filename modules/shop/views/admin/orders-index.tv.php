@extends('shop::layouts.admin')

@section('title', 'سفارش‌ها')

@section('content')
    <div class="topline">
        <h1>🧾 سفارش‌ها ({{ trans_number($total) }})</h1>
        <a href="/admin/shop/orders/sales.csv" class="btn ghost sm" style="margin-inline-start:auto">⬇ گزارش CSV</a>
    </div>

    <div class="stats">
        <div class="stat"><div class="num">{{ trans_number((int) ($stats['paid_count'] ?? 0)) }}</div><div class="muted">سفارش پرداخت‌شده</div></div>
        <div class="stat"><div class="num">{{ trans_number((int) ($stats['paid_total'] ?? 0)) }}</div><div class="muted">مجموع فروش (ریال)</div></div>
        @foreach($stats['by_status'] ?? [] as $st => $row)
            <div class="stat"><div class="num">{{ trans_number((int) $row['count']) }}</div><div class="muted">{{ $st }}</div></div>
        @endforeach
    </div>

    <form method="GET" class="card" style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" name="q" value="{{ $query }}" placeholder="شمارهٔ سفارش / کد رهگیری…" style="max-width:250px">
        <select name="status" style="max-width:170px">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت‌شده', 'shipped' => 'ارسال‌شده', 'delivered' => 'تحویل‌شده', 'canceled' => 'لغوشده', 'refunded' => 'مسترد'] as $val => $label)
                <option value="{{ $val }}" {{ $statusFilter === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn ghost sm" type="submit">فیلتر</button>
    </form>

    <div class="card" style="padding:0">
        <table class="data">
            <thead><tr><th>شماره</th><th>مشتری</th><th>تاریخ</th><th>مبلغ (ریال)</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><a href="/admin/shop/orders/{{ $order->id() }}"><strong>{{ $order->orderNumber() }}</strong></a></td>
                    <td>{{ trans_number($order->customerId()) }}</td>
                    <td>{{ $order->placedAt() !== null ? jalali($order->placedAt(), 'Y/m/d H:i') : '—' }}</td>
                    <td>{{ trans_number($order->total()->minor) }}</td>
                    <td>
                        <span class="badge {{ in_array($order->status()->value, ['paid','delivered'], true) ? 'green' : (in_array($order->status()->value, ['canceled','refunded'], true) ? 'red' : ($order->status()->value === 'shipped' ? 'amber' : '')) }}">{{ $order->status()->labelFa() }}</span>
                    </td>
                    <td><a class="btn ghost sm" href="/admin/shop/orders/{{ $order->id() }}">جزئیات</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">سفارشی وجود ندارد.</td></tr>
            @endforelse
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
                    <a href="?page={{ $p }}&q={{ urlencode($query) }}&status={{ urlencode($statusFilter) }}">{{ trans_number($p) }}</a>
                @endif
            @endfor
        </div>
    @endif
@endsection
