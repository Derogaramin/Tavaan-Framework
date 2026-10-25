@extends('erp::layouts.admin')

@section('title', 'تولید')

@section('content')
<div class="topline"><h1>🏗 تولید</h1></div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">سفارش‌های تولید</h2>
    <table class="data">
        <tr><th>شماره</th><th>محصول</th><th>برنامه</th><th>تولیدشده</th><th>بهای مواد</th><th>وضعیت</th><th></th></tr>
        @forelse($workOrders as $order)
            <tr>
                <td class="num">{{ $order->woNumber() }}</td>
                <td>{{ $itemNames[$order->productId()] ?? '—' }}</td>
                <td class="num">{{ trans_number($order->plannedQty()) }}</td>
                <td class="num">{{ trans_number($order->producedQty()) }}</td>
                <td class="num">{{ trans_number($order->materialCost()) }}</td>
                <td><span class="badge {{ $order->status()->value === 'completed' ? 'green' : 'amber' }}">{{ $order->status()->labelFa() }}</span></td>
                <td><a class="btn sm ghost" href="/admin/erp/production/work-orders/{{ $order->id() }}">مشاهده</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">سفارش تولیدی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    @include('erp::partials.pagination', ['baseUrl' => '/admin/erp/production', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">➕ سفارش تولید جدید</h2>
    <form method="post" action="/admin/erp/production/work-orders">
        @csrf
        <div class="form-grid">
            <label>محصول (با BOM فعال)
                <select name="product_id" required>
                    @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                </select>
            </label>
            <label>انبار مواد/محصول
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>مقدار برنامه<input type="number" name="planned_qty" min="0.001" step="0.001" required></label>
            <label></label>
        </div>
        <button class="btn" type="submit">برنامه‌ریزی تولید</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🧩 BOMها</h2>
    <table class="data">
        <tr><th>محصول</th><th>نسخه</th><th>اجزاء</th><th>وضعیت</th></tr>
        @forelse($boms as $bom)
            <tr>
                <td>{{ $itemNames[$bom->productId()] ?? $bom->productId() }}</td>
                <td class="num">{{ trans_number($bom->version()) }}</td>
                <td class="muted">
                    @foreach($bom->components() as $component)
                        {{ $itemNames[$component['item_id']] ?? $component['item_id'] }} × {{ trans_number($component['qty']) }}<br>
                    @endforeach
                </td>
                <td>@if($bom->isActive())<span class="badge green">فعال</span>@else<span class="badge">قدیمی</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">BOM ثبت نشده است.</td></tr>
        @endforelse
    </table>
    <form method="post" action="/admin/erp/production/bom" style="margin-top:10px">
        @csrf
        <div class="form-grid">
            <label>محصول
                <select name="product_id" required>
                    @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                </select>
            </label>
            <label></label>
        </div>
        <table class="data">
            <tr><th>جزء</th><th style="width:140px">مقدار به‌ازای هر محصول</th></tr>
            @foreach([0, 1, 2] as $i)
            <tr>
                <td>
                    <select name="component_id[]">
                        <option value="">—</option>
                        @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                    </select>
                </td>
                <td><input type="number" name="component_qty[]" min="0" step="0.001" value="0"></td>
            </tr>
            @endforeach
        </table>
        <button class="btn sm" type="submit" style="margin-top:8px">ثبت BOM</button>
    </form>
</div>
@endsection
