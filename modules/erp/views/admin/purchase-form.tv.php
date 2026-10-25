@extends('erp::layouts.admin')

@section('title', 'سفارش خرید جدید')

@section('content')
<div class="topline"><h1>🛒 سفارش خرید جدید</h1></div>

<div class="card">
    <form method="post" action="/admin/erp/purchases">
        @csrf
        <div class="form-grid">
            <label>تأمین‌کننده
                <select name="supplier_id" required>
                    <option value="">— انتخاب —</option>
                    @foreach($suppliers as $supplier)<option value="{{ $supplier->id() }}">{{ $supplier->name() }}</option>@endforeach
                </select>
            </label>
            <label></label>
        </div>
        <table class="data">
            <tr><th>کالا</th><th style="width:120px">مقدار</th><th style="width:180px">قیمت واحد (ریال)</th></tr>
            @foreach([0, 1, 2] as $i)
            <tr>
                <td>
                    <select name="item_id[]">
                        <option value="">—</option>
                        @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                    </select>
                </td>
                <td><input type="number" name="qty[]" min="0" step="0.001" value="0"></td>
                <td><input type="number" name="unit_price[]" min="0" step="1" value="0"></td>
            </tr>
            @endforeach
        </table>
        <p class="muted" style="margin:8px 0">
            پس از «ثبت برای تأیید»: مبلغ ≤ {{ trans_number($router->financeThreshold()) }} ریال → تأیید خودکار؛
            بیشتر → مدیر مالی؛ بالای {{ trans_number($router->ceoThreshold()) }} ریال → مدیر مالی سپس مدیرعامل.
        </p>
        <button class="btn" type="submit">ایجاد سفارش</button>
        <a class="btn ghost" href="/admin/erp/purchases">انصراف</a>
    </form>
</div>
@endsection
