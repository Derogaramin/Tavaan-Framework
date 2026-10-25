@extends('erp::layouts.admin')

@section('title', 'سفارش فروش جدید')

@section('content')
<div class="topline"><h1>💼 سفارش فروش جدید</h1></div>

<div class="card">
    <form method="post" action="/admin/erp/sales">
        @csrf
        <div class="form-grid">
            <label>مشتری
                <select name="customer_id" required>
                    <option value="">— انتخاب —</option>
                    @foreach($customers as $customer)<option value="{{ $customer->id() }}">{{ $customer->name() }}</option>@endforeach
                </select>
            </label>
            <label>انبار
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
        </div>
        <table class="data">
            <tr><th>کالا</th><th style="width:120px">مقدار</th><th style="width:180px">قیمت فروش (ریال)</th></tr>
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
        <button class="btn" type="submit" style="margin-top:8px">ایجاد و قطعی‌کردن سفارش</button>
        <a class="btn ghost" href="/admin/erp/sales">انصراف</a>
    </form>
</div>
@endsection
