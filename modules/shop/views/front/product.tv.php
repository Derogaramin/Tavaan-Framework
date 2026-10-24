@extends('shop::layouts.shop')

@section('title', $product->title())

@section('content')
    <?php
    $fmt = fn (int $minor): string => trans_number($minor) . ' ریال';
    $attrs = $product->attributes();
    ?>

    <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
        <div class="card" style="flex:0 0 280px;text-align:center;font-size:4rem">📦</div>

        <div class="card" style="flex:1;min-width:280px">
            <h1 style="font-size:1.25rem;margin-bottom:6px">{{ $product->title() }}</h1>
            @if($category !== null)
                <p class="muted">دسته: <a href="/shop/categories/{{ $category->slug() }}">{{ $category->title() }}</a>
                @if($product->sku()) • کد کالا: {{ $product->sku() }} @endif</p>
            @endif
            @if($product->summary())
                <p style="margin:10px 0">{!! $product->summary() !!}</p>
            @endif

            <div class="price-row" style="margin:12px 0">
                @if($product->isOnSale())
                    <span class="price" style="font-size:1.2rem">{{ $fmt($product->effectivePrice()->minor) }}</span>
                    <span class="old-price">{{ $fmt($product->price()->minor) }}</span>
                    <span class="badge sale">تخفیف</span>
                @else
                    <span class="price" style="font-size:1.2rem">{{ $fmt($product->effectivePrice()->minor) }}</span>
                @endif
            </div>

            @if($product->isOutOfStock())
                <span class="badge oos">ناموجود</span>
            @elseif($product->isLowStock())
                <span class="badge">فقط {{ trans_number($product->stock()) }} عدد باقی مانده</span>
            @else
                <span class="badge" style="background:#dcfce7;color:#14532d">موجود</span>
            @endif

            <form action="/shop/cart/items" method="POST" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id() }}">
                @if(count($variants) > 0)
                    <label style="align-self:center">تنوع:
                        <select name="variant_id" style="padding:6px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                            @foreach($variants as $variant)
                                <option value="{{ $variant->id() }}" {{ $variant->stock() < 1 ? 'disabled' : '' }}>
                                    {{ $variant->title() }} ({{ $fmt($variant->priceFor($product->effectivePrice())->minor) }}){{ $variant->stock() < 1 ? ' — ناموجود' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label style="align-self:center">تعداد:
                    <input type="number" name="quantity" value="1" min="1" style="width:64px;padding:6px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                </label>
                <button class="btn accent" type="submit" {{ ($product->isPurchasable() || count($variants) > 0) ? '' : 'disabled' }}>🛒 افزودن به سبد</button>
            </form>

            @if(count($attrs) > 0)
                <h2 style="font-size:1rem;margin:18px 0 6px">مشخصات</h2>
                <table class="data">
                    <tbody>
                    @foreach($attrs as $key => $value)
                        <tr><th style="width:35%">{{ $key }}</th><td>{{ is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($body !== '')
        <div class="card">
            <h2 style="font-size:1.05rem;margin-bottom:8px">توضیحات محصول</h2>
            <div class="prose">{!! $body !!}</div>
        </div>
    @endif

    @if(count($related) > 0)
        <div class="card">
            <h2 style="font-size:1.05rem;margin-bottom:10px">محصولات مرتبط</h2>
            <div class="grid">
                @foreach($related as $rel)
                    <div class="product-card">
                        <div class="thumb" style="height:80px;font-size:1.8rem">📦</div>
                        <div class="body">
                            <h3><a href="/shop/products/{{ $rel->slug() }}">{{ $rel->title() }}</a></h3>
                            <span class="price">{{ $fmt($rel->effectivePrice()->minor) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card" id="reviews">
        <h2 style="font-size:1.05rem;margin-bottom:10px">نقد و بررسی ({{ trans_number(count($thread)) }})</h2>

        @if(empty($thread))
            <p class="muted">هنوز نقدی ثبت نشده — اولین نفر باشید.</p>
        @else
            @php
                $renderNodes = function (array $nodes) use (&$renderNodes): string {
                    $html = '';
                    foreach ($nodes as $node) {
                        $c = $node->comment;
                        $name = htmlspecialchars($c->authorName, ENT_QUOTES, 'UTF-8');
                        $body = nl2br(htmlspecialchars($c->body, ENT_QUOTES, 'UTF-8'));
                        $date = $c->createdAt !== null ? jalali($c->createdAt, 'Y/m/d H:i') : '';
                        $html .= '<div style="border-bottom:1px solid var(--border);padding:10px 0" id="review-' . $c->id . '">';
                        $html .= '<strong>' . $name . '</strong> <span class="muted">— ' . $date . '</span>';
                        $html .= '<p style="margin-top:4px">' . $body . '</p>';
                        if ($node->children !== []) {
                            $html .= '<div style="margin-inline-start:24px;border-inline-start:3px solid var(--border);padding-inline-start:10px;margin-top:8px">' . $renderNodes($node->children) . '</div>';
                        }
                        $html .= '</div>';
                    }
                    return $html;
                };
            @endphp
            {!! $renderNodes($thread) !!}
        @endif

        <form action="/shop/products/{{ $product->slug() }}/reviews" method="POST" style="margin-top:12px">
            @csrf
            @guest
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                    <input type="text" name="author_name" placeholder="نام شما" required style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                    <input type="email" name="author_email" placeholder="ایمیل" required style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit">
                </div>
            @endguest
            <textarea name="body" rows="3" placeholder="نقد شما…" required style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);font-family:inherit"></textarea>
            <button class="btn sm" type="submit" style="margin-top:6px">ثبت نقد</button>
        </form>
    </div>
@endsection
