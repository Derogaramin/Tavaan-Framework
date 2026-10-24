@extends('shop::layouts.shop')

@section('title', isset($category) ? ('دستهٔ ' . $category->title()) : (isset($query) && $query !== '' ? 'جست‌وجو' : 'محصولات'))

@section('content')
    <?php $fmt = fn (int $minor): string => trans_number($minor) . ' ریال'; ?>

    @if(isset($query) && $query !== '')
        <h1 class="page-title">نتایج جست‌وجو برای «{{ $query }}» ({{ trans_number($total) }})</h1>
    @elseif(isset($category))
        <h1 class="page-title">دستهٔ {{ $category->title() }} ({{ trans_number($total) }})</h1>
    @else
        <h1 class="page-title">محصولات ({{ trans_number($total) }})</h1>
    @endif

    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <aside style="flex:0 0 200px">
            <div class="card">
                <strong>دسته‌ها</strong>
                <ul style="list-style:none;margin-top:8px;font-size:.9rem">
                    <li style="margin-bottom:4px"><a href="/shop">همهٔ محصولات</a></li>
                    @foreach($categories as $cat)
                        <li style="margin-bottom:4px;{{ $cat->parentId() !== null ? 'padding-inline-start:12px' : '' }}">
                            <a href="/shop/categories/{{ $cat->slug() }}">{{ $cat->title() }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <strong>مرتب‌سازی</strong>
                <ul style="list-style:none;margin-top:8px;font-size:.9rem">
                    <li><a href="?sort=newest">جدیدترین</a></li>
                    <li><a href="?sort=price_asc">ارزان‌ترین</a></li>
                    <li><a href="?sort=price_desc">گران‌ترین</a></li>
                    <li><a href="?sort=popular">پرفروش‌ترین</a></li>
                </ul>
            </div>
        </aside>

        <section style="flex:1;min-width:280px">
            @if(count($products) === 0)
                <div class="card muted">محصولی یافت نشد.</div>
            @else
                <div class="grid">
                    @foreach($products as $product)
                        <div class="product-card">
                            <div class="thumb">📦</div>
                            <div class="body">
                                <h3><a href="/shop/products/{{ $product->slug() }}">{{ $product->title() }}</a></h3>
                                <div class="price-row">
                                    @if($product->isOnSale())
                                        <span class="price">{{ $fmt($product->effectivePrice()->minor) }}</span>
                                        <span class="old-price">{{ $fmt($product->price()->minor) }}</span>
                                        <span class="badge sale">تخفیف</span>
                                    @else
                                        <span class="price">{{ $fmt($product->effectivePrice()->minor) }}</span>
                                    @endif
                                </div>
                                @if($product->isOutOfStock())
                                    <span class="badge oos">ناموجود</span>
                                @elseif($product->isLowStock())
                                    <span class="badge">فقط {{ trans_number($product->stock()) }} عدد</span>
                                @endif
                                @if($product->isFeatured())
                                    <span class="badge">پیشنهاد ویژه</span>
                                @endif
                                <form action="/shop/cart/items" method="POST" style="margin-top:auto">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id() }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="btn sm" type="submit" {{ $product->isPurchasable() ? '' : 'disabled' }}>افزودن به سبد</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($total > $perPage)
                    <?php $pages = (int) ceil($total / $perPage); ?>
                    <div class="pagination">
                        @for($p = 1; $p <= $pages; $p++)
                            @if($p === $page)
                                <span class="current">{{ trans_number($p) }}</span>
                            @else
                                <a href="?page={{ $p }}{{ (isset($query) && $query !== '') ? '&q=' . urlencode($query) : '' }}">{{ trans_number($p) }}</a>
                            @endif
                        @endfor
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
