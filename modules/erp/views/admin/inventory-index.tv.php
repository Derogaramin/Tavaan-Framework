@extends('erp::layouts.admin')

@section('title', 'انبار و موجودی')

@section('content')
<div class="topline">
    <h1>📦 انبار و موجودی</h1>
    <span class="spacer"></span>
    <form method="get" class="inline" style="display:flex;gap:6px">
        <input type="text" name="q" value="{{ $q }}" placeholder="جست‌وجوی کالا…">
        <button class="btn sm ghost" type="submit">جست‌وجو</button>
    </form>
</div>

@if(count($lowStock) > 0)
<div class="flash error">⚠ {{ trans_number(count($lowStock)) }} کالا زیر نقطهٔ سفارش است.</div>
@endif

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">انبارها</h2>
    <table class="data">
        <tr><th>کد</th><th>نام</th><th>نشانی</th></tr>
        @forelse($warehouses as $warehouse)
            <tr><td>{{ $warehouse->code() }}</td><td>{{ $warehouse->name() }}</td><td>{{ $warehouse->address() ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">انباری ثبت نشده است.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2 style="font-size:.95rem;margin-bottom:8px">کالاها و موجودی</h2>
    <table class="data">
        <tr><th>SKU</th><th>نام</th><th>نوع</th><th>واحد</th><th>بهای میانگین</th><th>موجودی (به تفکیک انبار)</th><th>کل</th></tr>
        @forelse($items as $item)
            @php
                $kindFa = $item->kind() === 'raw_material' ? 'مادهٔ اولیه' : ($item->kind() === 'finished' ? 'محصول' : 'کالای تجاری');
                $totalQty = 0.0;
                foreach (($stockByItem[$item->id()] ?? []) as $row) { $totalQty += $row['quantity']; }
            @endphp
            <tr>
                <td>{{ $item->sku() }}</td>
                <td>{{ $item->name() }}</td>
                <td><span class="badge">{{ $kindFa }}</span></td>
                <td>{{ $item->unit() }}</td>
                <td class="num">{{ trans_number($item->avgCost()) }}</td>
                <td>
                    @forelse($stockByItem[$item->id()] ?? [] as $row)
                        <span class="muted">{{ $row['warehouse_name'] }}:</span> <span class="num">{{ trans_number($row['quantity']) }}</span><br>
                    @empty
                        <span class="muted">—</span>
                    @endforelse
                </td>
                <td class="num {{ $totalQty <= $item->reorderPoint() && $item->reorderPoint() > 0 ? 'neg' : '' }}">{{ trans_number($totalQty) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">کالایی ثبت نشده است.</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">📥 رسید انبار</h2>
    <form method="post" action="/admin/erp/inventory/receive">
        @csrf
        <div class="form-grid">
            <label>انبار
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>نوع مرجع
                <select name="reference_type">
                    <option value="manual_receipt">رسید دستی (سود انبارداری)</option>
                    <option value="opening">موجودی افتتاحیه (سرمایه)</option>
                </select>
            </label>
        </div>
        <table class="data">
            <tr><th>کالا</th><th style="width:120px">مقدار</th><th style="width:180px">بهای واحد (ریال)</th></tr>
            @foreach([0, 1] as $i)
            <tr>
                <td>
                    <select name="item_id[]">
                        <option value="">—</option>
                        @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                    </select>
                </td>
                <td><input type="number" name="qty[]" min="0" step="0.001" value="0"></td>
                <td><input type="number" name="unit_cost[]" min="0" step="1" value="0" placeholder="پیش‌فرض: میانگین جاری"></td>
            </tr>
            @endforeach
        </table>
        <button class="btn" type="submit" style="margin-top:8px">ثبت رسید</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">📤 حوالهٔ انبار</h2>
    <form method="post" action="/admin/erp/inventory/issue">
        @csrf
        <div class="form-grid">
            <label>انبار
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>نوع مرجع
                <select name="reference_type">
                    <option value="manual_issue">حوالهٔ دستی (زیان انبارداری)</option>
                </select>
            </label>
        </div>
        <table class="data">
            <tr><th>کالا</th><th style="width:120px">مقدار</th></tr>
            @foreach([0, 1] as $i)
            <tr>
                <td>
                    <select name="item_id[]">
                        <option value="">—</option>
                        @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                    </select>
                </td>
                <td><input type="number" name="qty[]" min="0" step="0.001" value="0"></td>
            </tr>
            @endforeach
        </table>
        <button class="btn" type="submit" style="margin-top:8px">ثبت حواله</button>
        <p class="muted" style="margin-top:6px">بهای حواله = میانگین موزون جاری؛ سند بهای فروش/زیان خودکار صادر می‌شود.</p>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🔁 انتقال بین انبارها</h2>
    <form method="post" action="/admin/erp/inventory/transfer">
        @csrf
        <div class="form-grid">
            <label>از انبار
                <select name="from_warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>به انبار
                <select name="to_warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>کالا
                <select name="item_id" required>
                    @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                </select>
            </label>
            <label>مقدار<input type="number" name="qty" min="0.001" step="0.001" required></label>
        </div>
        <button class="btn" type="submit">انتقال</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">🧮 اصلاح موجودی (شمارش)</h2>
    <form method="post" action="/admin/erp/inventory/adjust">
        @csrf
        <div class="form-grid">
            <label>انبار
                <select name="warehouse_id" required>
                    @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id() }}">{{ $warehouse->name() }}</option>@endforeach
                </select>
            </label>
            <label>کالا
                <select name="item_id" required>
                    @foreach($items as $item)<option value="{{ $item->id() }}">{{ $item->sku() }} — {{ $item->name() }}</option>@endforeach
                </select>
            </label>
            <label>تغییر (±)<input type="number" name="delta" step="0.001" required placeholder="مثلاً 5- یا 3"></label>
            <label>یادداشت<input type="text" name="note" maxlength="255"></label>
        </div>
        <button class="btn" type="submit">ثبت اصلاح</button>
    </form>
</div>

<div class="card">
    <h2 style="font-size:1rem;margin-bottom:10px">➕ انبار/کالای جدید</h2>
    <form method="post" action="/admin/erp/inventory/warehouses">
        @csrf
        <div class="form-grid">
            <label>کد انبار<input type="text" name="code" required maxlength="50"></label>
            <label>نام انبار<input type="text" name="name" required maxlength="190"></label>
        </div>
        <button class="btn sm" type="submit">ایجاد انبار</button>
    </form>
    <hr style="margin:14px 0;border:0;border-top:1px solid #e2e8f0">
    <form method="post" action="/admin/erp/inventory/items">
        @csrf
        <div class="form-grid">
            <label>SKU<input type="text" name="sku" required maxlength="64"></label>
            <label>نام کالا<input type="text" name="name" required maxlength="255"></label>
            <label>واحد<input type="text" name="unit" maxlength="20" value="عدد"></label>
            <label>نوع
                <select name="kind" required>
                    <option value="merchandise">کالای تجاری</option>
                    <option value="raw_material">مادهٔ اولیه</option>
                    <option value="finished">محصول</option>
                </select>
            </label>
            <label>بهای اولیهٔ میانگین (ریال)<input type="number" name="avg_cost" min="0" step="1" value="0"></label>
            <label>نقطهٔ سفارش<input type="number" name="reorder_point" min="0" step="0.001" value="0"></label>
        </div>
        <button class="btn sm" type="submit" style="margin-top:8px">ایجاد کالا</button>
    </form>
</div>
@endsection
