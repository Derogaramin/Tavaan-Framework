@extends('shop::layouts.admin')

@section('title', $product !== null ? ('ویرایش: ' . $product->title()) : 'محصول جدید')

@section('content')
    <div class="topline">
        <h1>{{ $product !== null ? '✏ ویرایش محصول' : '+ محصول جدید' }}</h1>
        @if($product !== null)
            <a href="/shop/products/{{ $product->slug() }}" target="_blank" class="btn ghost sm" style="margin-inline-start:auto">مشاهده در ویترین</a>
        @endif
    </div>

    <form method="POST" action="{{ $product !== null ? '/admin/shop/products/' . $product->id() : '/admin/shop/products' }}" class="card">
        @csrf
        @if($product !== null)
            @method('PUT')
        @endif
        <div class="form-grid">
            <label class="full">عنوان محصول *
                <input type="text" name="title" required value="{{ $product?->title() ?? '' }}">
            </label>
            <label>دسته *
                <select name="category_id" required>
                    <option value="">— انتخاب کنید —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id() }}" {{ $product !== null && $product->categoryId() === $cat->id() ? 'selected' : '' }}>
                            {{ $cat->title() }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>وضعیت
                <select name="status">
                    @foreach(['draft' => 'پیش‌نویس', 'active' => 'فعال', 'archived' => 'بایگانی'] as $val => $label)
                        <option value="{{ $val }}" {{ $product !== null && $product->status()->value === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>قیمت (ریال) *
                <input type="number" name="price" min="0" required value="{{ $product !== null ? $product->price()->minor : '' }}">
            </label>
            <label>قیمت فروش (ریال، اختیاری)
                <input type="number" name="sale_price" min="0" value="{{ $product?->salePrice()?->minor ?? '' }}">
            </label>
            <label>کد کالا (SKU)
                <input type="text" name="sku" value="{{ $product?->sku() ?? '' }}">
            </label>
            <label>موجودی
                <input type="number" name="stock" min="0" value="{{ $product?->stock() ?? 0 }}" {{ $product !== null ? '' : '' }}>
            </label>
            <label>آستانهٔ هشدار موجودی
                <input type="number" name="low_stock_threshold" min="0" value="{{ $product?->lowStockThreshold() ?? 5 }}">
            </label>
            <label>وزن (kg، برای ارسال)
                <input type="number" name="weight" step="0.001" min="0" value="{{ $product?->weight() ?? '' }}">
            </label>
            <label style="flex-direction:row;align-items:center;gap:6px">
                <input type="checkbox" name="is_featured" value="1" style="width:auto" {{ $product !== null && $product->isFeatured() ? 'checked' : '' }}>
                پیشنهاد ویژه
            </label>
            <label class="full">خلاصه (Markdown)
                <textarea name="summary" rows="2">{{ $product !== null ? strip_tags((string) $product->summary()) : '' }}</textarea>
            </label>
            <label class="full">توضیحات (Markdown)
                <textarea name="description" rows="6">{{ $product !== null ? strip_tags((string) $product->description()) : '' }}</textarea>
            </label>
            <label class="full">ویژگی‌های پویا (JSON)
                <textarea name="attributes" rows="2" placeholder='{"رنگ":"قرمز","گارانتی":"۱۸ ماه"}'>{{ $product !== null && $product->attributes() !== [] ? json_encode($product->attributes(), JSON_UNESCAPED_UNICODE) : '' }}</textarea>
            </label>
            @if($product === null)
                <label>ULID تصویر شاخص (از رسانه‌ها)
                    <input type="text" name="featured_image" maxlength="26">
                </label>
            @else
                <label>ULID تصویر شاخص
                    <input type="text" name="featured_image" maxlength="26" value="{{ $product->featuredImage() ?? '' }}">
                </label>
            @endif
        </div>
        <button class="btn" type="submit" style="margin-top:14px">{{ $product !== null ? 'ذخیرهٔ تغییرات' : 'ساخت محصول' }}</button>
    </form>

    @if($product !== null)
        <div class="card">
            <h2 style="font-size:1rem;margin-bottom:10px">🎨 تنوع‌ها ({{ trans_number(count($variants)) }})</h2>
            @if(count($variants) > 0)
                <table class="data">
                    <thead><tr><th>عنوان</th><th>SKU</th><th>اختلاف قیمت</th><th>موجودی</th><th>گزینه‌ها</th><th></th></tr></thead>
                    <tbody>
                    @foreach($variants as $variant)
                        <tr>
                            <td>{{ $variant->title() }}</td>
                            <td>{{ $variant->sku() ?? '—' }}</td>
                            <td>{{ trans_number($variant->priceDelta()->minor) }}</td>
                            <td>{{ trans_number($variant->stock()) }}</td>
                            <td class="muted">{{ $variant->options() !== [] ? json_encode($variant->options(), JSON_UNESCAPED_UNICODE) : '—' }}</td>
                            <td>
                                <form action="/admin/shop/products/{{ $product->id() }}/variants/{{ $variant->id() }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger sm" type="submit">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <form action="/admin/shop/products/{{ $product->id() }}/variants" method="POST" class="form-grid" style="margin-top:12px">
                @csrf
                <label>عنوان تنوع *<input type="text" name="title" required placeholder="مثلاً قرمز / L"></label>
                <label>SKU<input type="text" name="sku"></label>
                <label>اختلاف قیمت (ریال) *<input type="number" name="price_delta" required value="0"></label>
                <label>موجودی *<input type="number" name="stock" required min="0" value="0"></label>
                <label class="full">گزینه‌ها (JSON)<input type="text" name="options" placeholder='{"color":"قرمز","size":"L"}'></label>
                <div class="full"><button class="btn ghost sm" type="submit">+ افزودن تنوع</button></div>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size:1rem;margin-bottom:8px">📊 تنظیم موجودی (انبار)</h2>
            <p class="muted">موجودی فعلی: <strong>{{ trans_number($product->stock()) }}</strong> — تغییرات در حرکت‌نامه ثبت می‌شود.</p>
            <form action="/admin/shop/products/{{ $product->id() }}/inventory" method="POST" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
                @csrf
                <input type="number" name="delta" required placeholder="+۱۰ یا −۵" style="max-width:140px">
                <input type="text" name="note" placeholder="دلیل (مثلاً انبارگردانی)" style="max-width:260px">
                <button class="btn ghost sm" type="submit">اعمال</button>
            </form>
        </div>

        <div class="card">
            <h2 style="font-size:1rem;margin-bottom:8px">🖼 تصویر محصول</h2>
            <form action="/admin/shop/products/{{ $product->id() }}/media" method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @csrf
                <input type="file" name="image" accept="image/*" required>
                <button class="btn ghost sm" type="submit">بارگذاری</button>
            </form>
        </div>
    @endif
@endsection
