@extends('erp::layouts.admin')

@section('title', 'سفارش تولید ' . $order->woNumber())

@section('content')
@php $st = $order->status()->value; @endphp
<div class="topline">
    <h1>🏗 {{ $order->woNumber() }}</h1>
    <span class="spacer"></span>
    <span class="badge {{ $st === 'completed' ? 'green' : 'amber' }}">{{ $order->status()->labelFa() }}</span>
    @if($st === 'planned')
        <form method="post" action="/admin/erp/production/work-orders/{{ $order->id() }}/start" class="inline">@csrf<button class="btn sm" type="submit">شروع تولید (مصرف مواد)</button></form>
    @endif
</div>

<div class="card">
    <table class="data">
        <tr><th>محصول</th><td>{{ $product !== null ? $product->name() : $order->productId() }}</td></tr>
        <tr><th>مقدار برنامه</th><td class="num">{{ trans_number($order->plannedQty()) }}</td></tr>
        <tr><th>تولیدشده</th><td class="num">{{ trans_number($order->producedQty()) }}</td></tr>
        <tr><th>بهای مواد مصرفی</th><td class="num">{{ trans_number($order->materialCost()) }} ریال</td></tr>
        <tr><th>بهای هر واحد محصول</th><td class="num">{{ trans_number($order->unitCost()) }} ریال</td></tr>
    </table>
</div>

@if($bom !== null)
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">اجزاء (BOM نسخهٔ {{ trans_number($bom->version()) }})</h2>
    <table class="data">
        <tr><th>جزء</th><th>به‌ازای هر محصول</th><th>مصرف کل برنامه‌ریزی‌شده</th></tr>
        @foreach($bom->components() as $component)
            <tr>
                <td>{{ $itemNames[$component['item_id']] ?? $component['item_id'] }}</td>
                <td class="num">{{ trans_number($component['qty']) }}</td>
                <td class="num">{{ trans_number($component['qty'] * $order->plannedQty()) }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endif

@if($st === 'in_progress')
<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">✅ تکمیل تولید</h2>
    <form method="post" action="/admin/erp/production/work-orders/{{ $order->id() }}/complete">
        @csrf
        <div class="form-grid">
            <label>مقدار تولیدشده<input type="number" name="produced_qty" min="0.001" step="0.001" value="{{ $order->plannedQty() }}" required></label>
            <label></label>
        </div>
        <button class="btn success" type="submit">تکمیل (رسید محصول + سند بها)</button>
    </form>
</div>
@endif
@endsection
