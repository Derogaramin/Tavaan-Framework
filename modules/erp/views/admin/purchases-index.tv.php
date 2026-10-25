@extends('erp::layouts.admin')

@section('title', 'خرید')

@section('content')
<div class="topline">
    <h1>🛒 تدارکات — سفارش‌های خرید</h1>
    <span class="spacer"></span>
    <form method="get" class="inline">
        <select name="status" onchange="this.form.submit()">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(['draft' => 'پیش‌نویس', 'pending_finance' => 'در انتظار مالی', 'pending_ceo' => 'در انتظار مدیرعامل', 'approved' => 'تأییدشده', 'received' => 'رسیدشده', 'invoiced' => 'فاکتورشده', 'paid' => 'پرداخت‌شده', 'rejected' => 'ردشده'] as $value => $label)
                <option value="{{ $value }}" {{ $statusFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn" href="/admin/erp/purchases/create">سفارش خرید جدید</a>
</div>

<div class="card">
    <table class="data">
        <tr><th>شماره</th><th>تأمین‌کننده</th><th>مبلغ</th><th>وضعیت</th><th>تأیید مالی</th><th>تأیید CEO</th><th></th></tr>
        @forelse($orders as $order)
            <tr>
                <td class="num">{{ $order->poNumber() }}</td>
                <td>{{ $supplierNames[$order->supplierId()] ?? '—' }}</td>
                <td class="num">{{ trans_number($order->total()) }} ریال</td>
                <td><span class="badge {{ $order->status()->value === 'approved' || $order->status()->value === 'paid' ? 'green' : ($order->status()->value === 'rejected' ? 'red' : 'amber') }}">{{ $order->status()->labelFa() }}</span></td>
                <td>{{ $order->financeApprovedBy() !== null ? '✓' : '—' }}</td>
                <td>{{ $order->ceoApprovedBy() !== null ? '✓' : '—' }}</td>
                <td><a class="btn sm ghost" href="/admin/erp/purchases/{{ $order->id() }}">مشاهده</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">سفارش خریدی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    @include('erp::partials.pagination', ['baseUrl' => '/admin/erp/purchases', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🏢 تأمین‌کنندگان</h2>
    <table class="data">
        <tr><th>کد</th><th>نام</th><th>تلفن</th><th>ماندهٔ حساب</th></tr>
        @foreach($suppliers as $supplier)
            <tr>
                <td>{{ $supplier->code() }}</td>
                <td>{{ $supplier->name() }}</td>
                <td class="num">{{ $supplier->phone() ?? '—' }}</td>
                <td class="num">—</td>
            </tr>
        @endforeach
    </table>
    <form method="post" action="/admin/erp/suppliers" style="margin-top:10px">
        @csrf
        <div class="form-grid">
            <label>کد<input type="text" name="code" required maxlength="50"></label>
            <label>نام<input type="text" name="name" required maxlength="190"></label>
            <label>تلفن<input type="text" name="phone" maxlength="20"></label>
            <label>ایمیل<input type="email" name="email" maxlength="190"></label>
        </div>
        <button class="btn sm" type="submit">افزودن تأمین‌کننده</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🧾 فاکتورهای تأمین‌کننده</h2>
    <table class="data">
        <tr><th>شماره</th><th>مبلغ</th><th>وضعیت</th><th></th></tr>
        @forelse($invoices as $invoice)
            <tr>
                <td class="num">{{ $invoice->invoiceNumber() }}</td>
                <td class="num">{{ trans_number($invoice->amount()) }} ریال</td>
                <td>@if($invoice->isPaid())<span class="badge green">پرداخت‌شده</span>@else<span class="badge amber">باز</span>@endif</td>
                <td>
                    @if(!$invoice->isPaid())
                        <form method="post" action="/admin/erp/supplier-invoices/{{ $invoice->id() }}/pay" class="inline">
                            @csrf
                            <select name="method" style="width:auto;display:inline-block">
                                <option value="bank">بانک</option>
                                <option value="cash">صندوق</option>
                            </select>
                            <button class="btn sm success" type="submit">پرداخت</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">فاکتوری ثبت نشده است.</td></tr>
        @endforelse
    </table>
    <form method="post" action="/admin/erp/supplier-invoices" style="margin-top:10px">
        @csrf
        <div class="form-grid">
            <label>تأمین‌کننده
                <select name="supplier_id" required>
                    @foreach($suppliers as $supplier)<option value="{{ $supplier->id() }}">{{ $supplier->name() }}</option>@endforeach
                </select>
            </label>
            <label>مبلغ (ریال)<input type="number" name="amount" min="1" step="1" required></label>
        </div>
        <button class="btn sm" type="submit">ثبت فاکتور تأمین‌کننده</button>
    </form>
</div>

<p class="muted">آستانه‌های تأیید: مالی {{ trans_number($router->financeThreshold()) }} ریال — مدیرعامل {{ trans_number($router->ceoThreshold()) }} ریال (DOC-097 §3).</p>
@endsection
