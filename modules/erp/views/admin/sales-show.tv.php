@extends('erp::layouts.admin')

@section('title', 'سفارش فروش ' . $order->orderNumber())

@section('content')
@php $st = $order->status()->value; @endphp
<div class="topline">
    <h1>💼 {{ $order->orderNumber() }}</h1>
    <span class="spacer"></span>
    <span class="badge {{ $st === 'invoiced' ? 'green' : 'amber' }}">{{ $order->status()->labelFa() }}</span>
    @if($st === 'draft')
        <form method="post" action="/admin/erp/sales/{{ $order->id() }}/confirm" class="inline">@csrf<button class="btn sm" type="submit">قطعی‌کردن</button></form>
    @endif
    @if($st === 'confirmed')
        <form method="post" action="/admin/erp/sales/{{ $order->id() }}/deliver" class="inline">@csrf<button class="btn sm success" type="submit">حواله و تحویل</button></form>
    @endif
    @if($st === 'delivered')
        <form method="post" action="/admin/erp/sales/{{ $order->id() }}/invoice" class="inline">@csrf<button class="btn sm success" type="submit">صدور فاکتور</button></form>
    @endif
</div>

<div class="card">
    <table class="data">
        <tr><th>مشتری</th><td>{{ $customer !== null ? $customer->name() : '—' }}</td></tr>
        <tr><th>مبلغ کل</th><td class="num">{{ trans_number($order->total()) }} ریال</td></tr>
        @if($order->deliveredAt() !== null)
            <tr><th>تحویل</th><td class="num">{{ $order->deliveredAt()->format('Y-m-d H:i') }}</td></tr>
        @endif
    </table>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">اقلام</h2>
    <table class="data">
        <tr><th>کالا</th><th>مقدار</th><th>تحویل‌شده</th><th>قیمت</th><th>بهای snapshot (COGS)</th></tr>
        @foreach($order->items() as $item)
            <tr>
                <td>{{ $itemNames[$item->itemId()] ?? $item->itemId() }}</td>
                <td class="num">{{ trans_number($item->qty()) }}</td>
                <td class="num">{{ trans_number($item->deliveredQty()) }}</td>
                <td class="num">{{ trans_number($item->unitPrice()) }}</td>
                <td class="num">{{ trans_number($item->unitCostSnapshot()) }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if(count($invoices) > 0)
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">فاکتورها</h2>
    <table class="data">
        <tr><th>شماره</th><th>مبلغ</th><th>COGS</th><th>وضعیت</th><th></th></tr>
        @foreach($invoices as $invoice)
            <tr>
                <td class="num">{{ $invoice->invoiceNumber() }}</td>
                <td class="num">{{ trans_number($invoice->amount()) }}</td>
                <td class="num">{{ trans_number($invoice->cogsAmount()) }}</td>
                <td>@if($invoice->isPaid())<span class="badge green">تسویه</span>@else<span class="badge amber">باز</span>@endif</td>
                <td>
                    @if(!$invoice->isPaid())
                        <form method="post" action="/admin/erp/sales-invoices/{{ $invoice->id() }}/receive-payment" class="inline">
                            @csrf
                            <select name="method" style="width:auto;display:inline-block"><option value="bank">بانک</option><option value="cash">صندوق</option></select>
                            <input type="number" name="amount" min="1" step="1" value="{{ $invoice->amount() }}" style="width:130px;display:inline-block">
                            <button class="btn sm success" type="submit">ثبت دریافت</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endif
@endsection
