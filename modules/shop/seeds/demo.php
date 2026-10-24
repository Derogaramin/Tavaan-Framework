<?php

declare(strict_types=1);

/*
 * ماژول فروشگاه — seed دمو (DOC-093؛ kit:install shop --with-demo).
 *
 * دادهٔ نمایشی: دسته‌ها، ۶ محصول (فعال/ویژه/ناموجود/پیش‌نویس)، variant
 * و دو کوپن. idempotent — با slug/code موجود، رد می‌شود.
 */

use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;
use Modules\Shop\Domain\Entities\ShopCategory;
use Modules\Shop\Domain\ValueObjects\Money;
use Tavaan\Core\Facade\Facades\DB;

return static function (): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    // ── دسته‌ها ───────────────────────────────────────────────────────────
    $categories = [];
    foreach ([
        ['title' => 'لوازم دیجیتال', 'slug' => 'digital', 'desc' => 'گوشی، لپ‌تاپ و لوازم جانبی'],
        ['title' => 'کتاب و لوازم‌التحریر', 'slug' => 'books', 'desc' => 'کتاب، دفتر و نوشت‌افزار'],
        ['title' => 'خانه و آشپزخانه', 'slug' => 'home', 'desc' => 'لوازم خانه و آشپزخانه'],
    ] as $cat) {
        $exists = DB::table('shop_categories')->where('slug', $cat['slug'])->first();
        if ($exists !== null) {
            $categories[$cat['slug']] = (int) $exists['id'];

            continue;
        }
        $entity = ShopCategory::create($cat['title'], $cat['slug'], null, $cat['desc']);
        $id = DB::table('shop_categories')->insertGetId([
            ...$entity->toRow(),
            'created_at' => $now->format('Y-m-d H:i:s.v'),
            'updated_at' => null,
        ]);
        $categories[$cat['slug']] = (int) $id;
    }

    // ── محصولات ───────────────────────────────────────────────────────────
    $products = [
        [
            'slug' => 'laptop-pars-15', 'title' => 'لپ‌تاپ پارس ۱۵ اینچ',
            'cat' => 'digital', 'price' => 485_000_000, 'sale' => 459_000_000,
            'stock' => 8, 'featured' => true, 'sku' => 'LAP-PARS-15',
            'summary' => 'لپ‌تاپ ۱۵ اینچی با پردازندهٔ نسل جدید، ۱۶ گیگ رم و ۵۱۲ گیگ حافظهٔ SSD.',
            'attrs' => ['پردازنده' => 'Core i7 نسل ۱۳', 'رم' => '۱۶ گیگابایت', 'حافظه' => '۵۱۲GB SSD', 'گارانتی' => '۱۸ ماه'],
        ],
        [
            'slug' => 'smartphone-simorgh-x', 'title' => 'گوشی سیمرغ X',
            'cat' => 'digital', 'price' => 219_000_000, 'sale' => null,
            'stock' => 25, 'featured' => true, 'sku' => 'PHN-SMQ-X',
            'summary' => 'گوشی هوشمند با دوربین ۶۴ مگاپیکسل و باتری ۵۰۰۰ میلی‌آمپر.',
            'attrs' => ['دوربین' => '۶۴ مگاپیکسل', 'باتری' => '۵۰۰۰mAh', 'حافظه' => '۱۲۸ گیگابایت'],
        ],
        [
            'slug' => 'wireless-headphone-ava', 'title' => 'هدفون بی‌سیم آوا',
            'cat' => 'digital', 'price' => 38_500_000, 'sale' => 32_900_000,
            'stock' => 40, 'featured' => false, 'sku' => 'AUD-AVA-BT',
            'summary' => 'هدفون بی‌سیم با حذف نویز فعال و ۳۰ ساعت شارژدهی.',
            'attrs' => ['نوع' => 'Over-ear', 'نویزگیری' => 'ANC فعال', 'شارژدهی' => '۳۰ ساعت'],
        ],
        [
            'slug' => 'shahnameh-deluxe', 'title' => 'شاهنامهٔ فردوسی (نسخهٔ نفیس)',
            'cat' => 'books', 'price' => 9_800_000, 'sale' => null,
            'stock' => 12, 'featured' => false, 'sku' => 'BOK-SHAH-DEL',
            'summary' => 'چاپ نفیس شاهنامه با توضیحات و تصویرگری‌های اصیل ایرانی.',
            'attrs' => ['قطع' => 'وزیری', 'صفحات' => '۹۸۰', 'جلد' => 'گالینگور'],
        ],
        [
            'slug' => 'notebook-set-7', 'title' => 'ست دفترچهٔ ۷ جلدی',
            'cat' => 'books', 'price' => 2_450_000, 'sale' => 1_990_000,
            'stock' => 0, 'featured' => false, 'sku' => 'STN-NB7',
            'summary' => 'مجموعهٔ هفت دفترچهٔ جیبی با کاغذ کرم و جلد سخت — ناموجود.',
            'attrs' => ['تعداد' => '۷ جلد', 'قطع' => 'جیبی'],
        ],
        [
            'slug' => 'espressopot-roha', 'title' => 'قهوه‌ساز اسپرسو رها',
            'cat' => 'home', 'price' => 76_000_000, 'sale' => null,
            'stock' => 5, 'featured' => false, 'sku' => 'HOM-RHA-ESP',
            'summary' => 'اسپرسوساز خانگی ۲۰ بار با مخزن شیر و سیستم کف‌ساز.',
            'attrs' => ['فشار' => '۲۰ بار', 'مخزن' => '۱.۵ لیتر', 'توان' => '۱۳۵۰ وات'],
        ],
    ];

    foreach ($products as $data) {
        if (DB::table('shop_products')->where('slug', $data['slug'])->exists()) {
            continue;
        }

        $product = Product::draft(
            slug: $data['slug'],
            title: $data['title'],
            categoryId: $categories[$data['cat']] ?? 1,
            price: Money::of($data['price'], 'IRR'),
            summary: '<p>' . $data['summary'] . '</p>',
            description: '<p>' . $data['summary'] . '</p>',
            sku: $data['sku'],
            stock: $data['stock'],
            createdAt: $now,
        );
        $product->activate($now);
        $product->revise(
            title: $data['title'],
            summary: '<p>' . $data['summary'] . '</p>',
            description: '<p>' . $data['summary'] . '</p>',
            categoryId: $categories[$data['cat']] ?? 1,
            price: Money::of($data['price'], 'IRR'),
            salePrice: $data['sale'] !== null ? Money::of($data['sale'], 'IRR') : null,
            sku: $data['sku'],
            attributes: $data['attrs'],
            isFeatured: $data['featured'],
            lowStockThreshold: 5,
            at: $now,
        );
        $product->activate($now);
        $product->pullEvents();

        DB::table('shop_products')->insert($product->toRow());

        // variant برای محصول اول (رنگ)
        if ($data['slug'] === 'smartphone-simorgh-x') {
            foreach ([['مشکی', 0, 'SMQ-X-BLK', ['color' => 'مشکی']], ['آبی', 2_000_000, 'SMQ-X-BLU', ['color' => 'آبی']]] as [$title, $delta, $sku, $options]) {
                $variant = ProductVariant::create($product->id(), $title, Money::of($delta, 'IRR'), 10, $sku, $options);
                DB::table('shop_product_variants')->insert($variant->toRow());
            }
        }
    }

    // ── کوپن‌ها ────────────────────────────────────────────────────────────
    if (!DB::table('shop_coupons')->where('code', 'TAVAAN10')->exists()) {
        $coupon = Coupon::create(
            code: 'TAVAAN10',
            type: 'percent',
            value: 10,
            maxDiscount: 50_000_000,
            minCart: 1_000_000,
            usageLimit: 500,
            expiresAt: new \DateTimeImmutable('+90 days', new \DateTimeZone('UTC')),
        );
        DB::table('shop_coupons')->insert([
            ...$coupon->toRow(),
            'created_at' => $now->format('Y-m-d H:i:s.v'),
            'updated_at' => null,
        ]);
    }
    if (!DB::table('shop_coupons')->where('code', 'OFF500K')->exists()) {
        $coupon = Coupon::create(
            code: 'OFF500K',
            type: 'fixed',
            value: 500_000,
            minCart: 5_000_000,
            usageLimit: 200,
            expiresAt: new \DateTimeImmutable('+60 days', new \DateTimeZone('UTC')),
        );
        DB::table('shop_coupons')->insert([
            ...$coupon->toRow(),
            'created_at' => $now->format('Y-m-d H:i:s.v'),
            'updated_at' => null,
        ]);
    }
};
