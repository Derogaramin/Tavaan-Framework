@extends('erp::layouts.admin')

@section('title', 'فروش')

@section('content')
<div class="topline">
    <h1>💼 فروش — سفارش‌ها</h1>
    <span class="spacer"></span>
    <form method="get" class="inline">
        <select name="status" onchange="this.form.submit()">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(['draft' => 'پیش‌نویس', 'confirmed' => 'قطعی', 'delivered' => 'تحویل‌شده', 'invoiced' => 'فاکتورشده', 'canceled' => 'لغوشده'] as $value => $label)
                <option value="{{ $value }}" {{ $statusFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <a class="btn" href="/admin/erp/sales/create">سفارش فروش جدید</a>
</div>

<div class="card">
    <table class="data">
        <tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th></th></tr>
        @forelse($orders as $order)
            <tr>
                <td class="num">{{ $order->orderNumber() }}</td>
                <td>{{ $customerNames[$order->customerId()] ?? '—' }}</td>
                <td class="num">{{ trans_number($order->total()) }} ریال</td>
                <td><span class="badge {{ $order->status()->value === 'invoiced' ? 'green' : 'amber' }}">{{ $order->status()->labelFa() }}</span></td>
                <td><a class="btn sm ghost" href="/admin/erp/sales/{{ $order->id() }}">مشاهده</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">سفارش فروشی ثبت نشده است.</td></tr>
        @endforelse
    </table>
    @include('erp::partials.pagination', ['baseUrl' => '/admin/erp/sales', 'total' => $total, 'page' => $page, 'perPage' => $perPage])
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🧾 فاکتورهای فروش</h2>
    <table class="data">
        <tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>COGS</th><th>وضعیت</th><th></th></tr>
        @forelse($invoices as $invoice)
            <tr>
                <td class="num">{{ $invoice->invoiceNumber() }}</td>
                <td>{{ $customerNames[$invoice->customerId()] ?? '—' }}</td>
                <td class="num">{{ trans_number($invoice->amount()) }}</td>
                <td class="num">{{ trans_number($invoice->cogsAmount()) }}</td>
                <td>@if($invoice->isPaid())<span class="badge green">تسویه</span>@else<span class="badge amber">باز</span>@endif</td>
                <td>
                    @if(!$invoice->isPaid())
                        <form method="post" action="/admin/erp/sales-invoices/{{ $invoice->id() }}/receive-payment" class="inline">
                            @csrf
                            <select name="method" style="width:auto;display:inline-block"><option value="bank">بانک</option><option value="cash">صندوق</option></select>
                            <button class="btn sm success" type="submit">ثبت دریافت</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">فاکتوری صادر نشده است.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">👤 مشتریان</h2>
    <table class="data">
        <tr><th>کد</th><th>نام</th><th>تلفن</th></tr>
        @foreach($customers as $customer)
            <tr><td>{{ $customer->code() }}</td><td>{{ $customer->name() }}</td><td class="num">{{ $customer->phone() ?? '—' }}</td></tr>
        @endforeach
    </table>
    <form method="post" action="/admin/erp/customers" style="margin-top:10px">
        @csrf
        <div class="form-grid">
            <label>کد<input type="text" name="code" required maxlength="50"></label>
            <label>نام<input type="text" name="name" required maxlength="190"></label>
            <label>تلفن<input type="text" name="phone" maxlength="20"></label>
            <label>ایمیل<input type="email" name="email" maxlength="190"></label>
        </div>
        <button class="btn sm" type="submit">افزودن مشتری</button>
    </form>
</div>
@endsection
