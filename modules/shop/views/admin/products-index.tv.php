@extends('shop::layouts.admin')

@section('title', 'محصولات')

@section('content')
    <div class="topline">
        <h1>📦 محصولات ({{ trans_number($total) }})</h1>
        <a href="/admin/shop/products/create" class="btn" style="margin-inline-start:auto">+ محصول جدید</a>
    </div>

    <form method="GET" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="text" name="q" value="{{ $query }}" placeholder="جست‌وجو (عنوان/sku)…" style="max-width:240px">
        <select name="status" style="max-width:160px">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(['draft' => 'پیش‌نویس', 'active' => 'فعال', 'archived' => 'بایگانی'] as $val => $label)
                <option value="{{ $val }}" {{ $statusFilter === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn ghost sm" type="submit">فیلتر</button>
    </form>

    <div class="card" style="padding:0">
        <table class="data">
            <thead>
            <tr><th>عنوان</th><th>دسته</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th>عملیات</th></tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <?php
                $cat = null;
                foreach ($categories as $c) { if ($c->id() === $product->categoryId()) { $cat = $c; break; } }
                ?>
                <tr>
                    <td>
                        <a href="/shop/products/{{ $product->slug() }}" target="_blank"><strong>{{ $product->title() }}</strong></a>
                        @if($product->sku())<div class="muted">{{ $product->sku() }}</div>@endif
                    </td>
                    <td>{{ $cat !== null ? $cat->title() : '—' }}</td>
                    <td>
                        {{ trans_number($product->effectivePrice()->minor) }}
                        @if($product->isOnSale())<span class="badge red">تخفیف</span>@endif
                    </td>
                    <td>
                        @if($product->isOutOfStock())
                            <span class="badge red">ناموجود</span>
                        @elseif($product->isLowStock())
                            <span class="badge amber">{{ trans_number($product->stock()) }}</span>
                        @else
                            {{ trans_number($product->stock()) }}
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $product->status()->value === 'active' ? 'green' : ($product->status()->value === 'archived' ? 'red' : '') }}">{{ $product->status()->labelFa() }}</span>
                    </td>
                    <td>
                        <a class="btn ghost sm" href="/admin/shop/products/{{ $product->id() }}/edit">ویرایش</a>
                        <form action="/admin/shop/products/{{ $product->id() }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger sm" type="submit" onclick="return confirm('حذف شود؟')">حذف</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">محصولی وجود ندارد.</td></tr>
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
