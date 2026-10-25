@extends('erp::layouts.admin')

@section('title', 'سفارش خرید ' . $order->poNumber())

@section('content')
@php $st = $order->status()->value; @endphp
<div class="topline">
    <h1>🛒 {{ $order->poNumber() }}</h1>
    <span class="spacer"></span>
    <span class="badge {{ $st === 'approved' || $st === 'paid' || $st === 'received' || $st === 'invoiced' ? 'green' : ($st === 'rejected' || $st === 'canceled' ? 'red' : 'amber') }}">{{ $order->status()->labelFa() }}</span>
    @if($st === 'draft')
        <form method="post" action="/admin/erp/purchases/{{ $order->id() }}/submit" class="inline">@csrf<button class="btn sm" type="submit">ثبت برای تأیید</button></form>
    @endif
    @if($st === 'pending_finance')
        <form method="post" action="/admin/erp/purchases/{{ $order->id() }}/approve-finance" class="inline">@csrf<button class="btn sm success" type="submit">تأیید مالی</button></form>
    @endif
    @if($st === 'pending_ceo')
        <form method="post" action="/admin/erp/purchases/{{ $order->id() }}/approve-ceo" class="inline">@csrf<button class="btn sm success" type="submit">تأیید مدیرعامل</button></form>
    @endif
    @if($st === 'pending_finance' || $st === 'pending_ceo')
        <form method="post" action="/admin/erp/purchases/{{ $order->id() }}/reject" class="inline" style="display:flex;gap:4px">
            @csrf<input type="text" name="reason" placeholder="دلیل رد" required maxlength="255"><button class="btn sm danger" type="submit">رد</button>
        </form>
    @endif
</div>

<div class="card">
    <table class="data">
        <tr><th>تأمین‌کننده</th><td>{{ $supplier !== null ? $supplier->name() : '—' }}</td></tr>
        <tr><th>مبلغ کل</th><td class="num">{{ trans_number($order->total()) }} ریال</td></tr>
        <tr><th>مسیر تأیید</th><td class="muted">
            @if($order->total() <= $router->financeThreshold())
                خودکار (زیر آستانهٔ مالی)
            @elseif($order->total() <= $router->ceoThreshold())
                مدیر مالی
            @else
                مدیر مالی → مدیرعامل
            @endif
        </td></tr>
        <tr><th>نمونهٔ workflow</th><td class="num">{{ $order->workflowInstanceId() ?? '—' }}</td></tr>
        @if($order->rejectReason() !== null)
            <tr><th>دلیل رد</th><td>{{ $order->rejectReason() }}</td></tr>
        @endif
    </table>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">اقلام</h2>
    <table class="data">
        <tr><th>کالا</th><th>مقدار</th><th>دریافت‌شده</th><th>قیمت واحد</th><th>جمع</th></tr>
        @foreach($order->items() as $item)
            <tr>
                <td>{{ $itemNames[$item->itemId()] ?? $item->itemId() }}</td>
                <td class="num">{{ trans_number($item->qty()) }}</td>
                <td class="num">{{ trans_number($item->receivedQty()) }}</td>
                <td class="num">{{ trans_number($item->unitPrice()) }}</td>
                <td class="num">{{ trans_number($item->lineTotal()) }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if($order->isApproved())
<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">📥 ثبت رسید کالا</h2>
    <form method="post" action="/admin/erp/purchases/{{ $order->id() }}/receive">
        @csrf
        <div class="form-grid">
            <label>انبار
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label></label>
        </div>
        <table class="data">
            <tr><th>کالا</th><th style="width:140px">مقدار</th></tr>
            @foreach($order->items() as $item)
            <tr>
                <td>
                    <input type="hidden" name="item_id[]" value="{{ $item->itemId() }}">
                    {{ $itemNames[$item->itemId()] ?? $item->itemId() }}
                    <span class="muted">(باقی‌مانده: {{ trans_number($item->qty() - $item->receivedQty()) }})</span>
                </td>
                <td><input type="number" name="qty[]" min="0" step="0.001" value="0"></td>
            </tr>
            @endforeach
        </table>
        <button class="btn" type="submit" style="margin-top:8px">ثبت رسید</button>
        <p class="muted" style="margin-top:6px">بها از قیمت سفارش؛ سند بدهکار موجودی / بستانکار پرداختنی خودکار (Outbox) صادر می‌شود.</p>
    </form>
</div>
@endif

@if(count($receipts) > 0)
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">رسیدهای این سفارش</h2>
    <table class="data">
        <tr><th>شماره</th><th>انبار</th><th>بهای کل</th><th>تاریخ</th></tr>
        @foreach($receipts as $receipt)
            <tr>
                <td class="num">{{ $receipt->receiptNumber() }}</td>
                <td class="num">{{ $receipt->warehouseId() }}</td>
                <td class="num">{{ trans_number($receipt->totalCost()) }}</td>
                <td class="num">{{ $receipt->createdAt()->format('Y-m-d H:i') }}</td>
            </tr>
        @endforeach
    </table>
</div>
@endif

@if(count($invoices) > 0)
<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">فاکتورهای تأمین‌کننده</h2>
    <table class="data">
        <tr><th>شماره</th><th>مبلغ</th><th>وضعیت</th><th></th></tr>
        @foreach($invoices as $invoice)
            <tr>
                <td class="num">{{ $invoice->invoiceNumber() }}</td>
                <td class="num">{{ trans_number($invoice->amount()) }}</td>
                <td>@if($invoice->isPaid())<span class="badge green">پرداخت‌شده</span>@else<span class="badge amber">باز</span>@endif</td>
                <td>
                    @if(!$invoice->isPaid())
                        <form method="post" action="/admin/erp/supplier-invoices/{{ $invoice->id() }}/pay" class="inline">
                            @csrf
                            <select name="method" style="width:auto;display:inline-block"><option value="bank">بانک</option><option value="cash">صندوق</option></select>
                            <button class="btn sm success" type="submit">پرداخت</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endif
@endsection
