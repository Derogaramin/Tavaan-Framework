<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول فروشگاه — DOC-093 §3.
 *
 * 🔒 پول: int minor-unit (ADR-0013 — هرگز float/DECIMAL در لایهٔ اپ؛
 * انطباق با DECIMAL(18,4) مستند در گزارش M9 گام ۳ ثبت شده است).
 * prefix ماژول = shop_ (DoD §3). FULLTEXT روی sqlite/pg → index معمولی.
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shop_categories')) {
            Schema::create('shop_categories', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('parent_id')->nullable();
                $t->string('title', 190);
                $t->string('slug', 200)->unique();
                $t->text('description')->nullable();
                $t->timestamps(3);
                $t->index(['parent_id'], 'idx_shop_cat_parent');
            });
        }

        if (!Schema::hasTable('shop_products')) {
            Schema::create('shop_products', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                     // ULID
                $t->string('slug', 160)->unique();
                $t->string('title', 255);
                $t->text('summary')->nullable();
                $t->longText('description')->nullable();
                $t->unsignedBigInteger('category_id');
                $t->unsignedBigInteger('brand_id')->nullable();
                $t->unsignedBigInteger('price')->default(0);       // minor-unit (ADR-0013)
                $t->unsignedBigInteger('sale_price')->nullable();
                $t->char('currency', 3)->default('IRR');
                $t->string('sku', 64)->nullable()->unique();
                $t->integer('stock')->default(0);
                $t->string('status', 20)->default('draft');        // draft|active|archived
                $t->boolean('is_featured')->default(false);
                $t->json('attributes')->nullable();                // ویژگی‌های پویا
                $t->char('featured_image', 26)->nullable();        // → tvn_media
                $t->decimal('weight', 10, 3)->nullable();           // برای ارسال
                $t->integer('view_count')->default(0);
                $t->integer('sold_count')->default(0);
                $t->decimal('rating_avg', 3, 2)->default(0);
                $t->integer('low_stock_threshold')->default(5);
                $t->dateTime('published_at', 3)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->dateTime('deleted_at', 3)->nullable();
                $t->index(['status', 'created_at'], 'idx_status_created');
                $t->index(['category_id', 'status'], 'idx_category');
                $t->fullText(['title', 'summary'], 'ft_search');
            });
        }

        if (!Schema::hasTable('shop_product_variants')) {
            Schema::create('shop_product_variants', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('product_id', 26);
                $t->string('sku', 64)->nullable();
                $t->string('title', 190);
                $t->bigInteger('price_delta')->default(0);         // minor-unit، می‌تواند منفی باشد
                $t->integer('stock')->default(0);
                $t->json('options')->nullable();                   // {"color":"قرمز","size":"L"}
                $t->index(['product_id'], 'idx_variant_product');
            });
        }

        if (!Schema::hasTable('shop_carts')) {
            Schema::create('shop_carts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('session_id', 64)->nullable();
                $t->string('coupon_code', 50)->nullable();
                $t->bigInteger('total')->default(0);               // جمع اقلام (minor-unit)
                $t->dateTime('expires_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['user_id'], 'idx_cart_user');
                $t->index(['session_id'], 'idx_cart_session');
            });
        }

        if (!Schema::hasTable('shop_cart_items')) {
            Schema::create('shop_cart_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('cart_id', 26);
                $t->char('product_id', 26);
                $t->char('variant_id', 26)->nullable();
                $t->integer('quantity')->default(1);
                $t->unsignedBigInteger('unit_price');              // snapshot قیمت در لحظهٔ افزودن
                $t->index(['cart_id'], 'idx_items_cart');
            });
        }

        if (!Schema::hasTable('shop_orders')) {
            Schema::create('shop_orders', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('order_number', 32)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->string('status', 20)->default('pending');      // pending|paid|shipped|delivered|canceled|refunded
                $t->bigInteger('subtotal')->default(0);
                $t->bigInteger('discount')->default(0);
                $t->bigInteger('tax')->default(0);
                $t->bigInteger('shipping_cost')->default(0);
                $t->bigInteger('total');
                $t->char('currency', 3)->default('IRR');
                $t->json('shipping_address')->nullable();
                $t->json('billing_address')->nullable();
                $t->string('payment_method', 50)->nullable();
                $t->string('payment_authority', 190)->nullable();  // authority درگاه
                $t->string('tracking_code', 100)->nullable();      // کد پستی
                $t->text('notes')->nullable();
                $t->dateTime('placed_at', 3)->nullable();
                $t->dateTime('paid_at', 3)->nullable();
                $t->dateTime('shipped_at', 3)->nullable();
                $t->dateTime('delivered_at', 3)->nullable();
                $t->dateTime('canceled_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['customer_id', 'created_at'], 'idx_customer');
                $t->index(['status'], 'idx_order_status');
            });
        }

        if (!Schema::hasTable('shop_order_items')) {
            Schema::create('shop_order_items', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('order_id', 26);
                $t->char('product_id', 26)->nullable();
                $t->char('variant_id', 26)->nullable();
                $t->string('title', 255);                          // snapshot
                $t->string('sku', 64)->nullable();
                $t->integer('quantity');
                $t->unsignedBigInteger('unit_price');
                $t->bigInteger('discount')->default(0);
                $t->bigInteger('total');
                $t->index(['order_id'], 'idx_order_items');
            });
        }

        if (!Schema::hasTable('shop_coupons')) {
            Schema::create('shop_coupons', function (Blueprint $t): void {
                $t->id();
                $t->string('code', 50)->unique();
                $t->string('type', 20)->default('percent');        // percent|fixed
                $t->unsignedBigInteger('value')->default(0);       // percent: 0..100 • fixed: minor-unit
                $t->unsignedBigInteger('max_discount')->nullable(); // سقف تخفیف (percent)
                $t->unsignedBigInteger('min_cart')->default(0);    // حداقل سبد
                $t->integer('usage_limit')->nullable();
                $t->integer('used_count')->default(0);
                $t->dateTime('expires_at', 3)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('shop_comments')) {
            Schema::create('shop_comments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('commentable_type', 100);
                $t->char('commentable_id', 26);
                $t->string('author_name', 100);
                $t->string('author_email', 190);
                $t->text('body');
                $t->char('parent_id', 26)->nullable();
                $t->string('status', 20)->default('pending');
                $t->dateTime('created_at', 3);
                $t->index(['commentable_type', 'commentable_id', 'status'], 'idx_shop_commentable');
            });
        }

        if (!Schema::hasTable('shop_stock_movements')) {
            Schema::create('shop_stock_movements', function (Blueprint $t): void {
                $t->id();
                $t->char('product_id', 26);
                $t->char('variant_id', 26)->nullable();
                $t->integer('delta');                              // +/-
                $t->integer('stock_after');
                $t->string('reason', 50);                          // reserve|release|adjust|sale
                $t->char('order_id', 26)->nullable();
                $t->string('note', 255)->nullable();
                $t->dateTime('created_at', 3);
                $t->index(['product_id'], 'idx_stock_product');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stock_movements');
        Schema::dropIfExists('shop_comments');
        Schema::dropIfExists('shop_coupons');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_cart_items');
        Schema::dropIfExists('shop_carts');
        Schema::dropIfExists('shop_product_variants');
        Schema::dropIfExists('shop_products');
        Schema::dropIfExists('shop_categories');
    }
};
