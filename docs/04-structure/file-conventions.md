# DOC-081 — قرارداد محتوای فایل‌ها (File Conventions) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-081 |
| والد | DOC-011 (Coding Style)، DOC-012 (Naming) |
| هدف | یکدستی ساختار درونی هر نوع فایل |

---

## ۱. الگوی کلی فایل PHP کلاس

```php
<?php

declare(strict_types=1);

/*──────────────────────────────────────────────────────────────────────────
│ تَوان | Tavaan — <Engine/Module>
│ <نام کلاس> — <مسئولیت یک‌خطی>
├──────────────────────────────────────────────────────────────────────────
│ @package   Tavaan\Engine\<X>
│ @author    Tavaan Team <team@tavaan.dev>
│ @license   MIT
│ @version   1.0.0
│ @since     1.0.0
│ @see       docs/03-engines/<x>.md
└─────────────────────────────────────────────────────────────────────────*/

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Core\Exceptions\Cache\StoreUnavailableException;

/**
 * خلاصهٔ یک‌خطی (Summary).
 *
 * توضیح بلندتر: چرا این کلاس وجود دارد، چه چیزی را حل می‌کند،
 * چه محدودیت‌هایی دارد و چگونه استفاده می‌شود.
 *
 * ### مثال
 * ```php
 * $store = new FileStore('/path/to/cache', prefix: 'tvn:');
 * $store->put('key', ['a' => 1], ttl: 60);
 * ```
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/cache.md
 */
final class FileStore implements StoreInterface
{
    /** @var int حداکثر اندازهٔ مقدار (بایت) */
    private const int MAX_VALUE_SIZE = 5_242_880;

    private const int DIR_PERMISSION  = 0775;
    private const int FILE_PERMISSION = 0664;

    /**
     * @param string $directory مسیر پایهٔ کش (باید وجود داشته و قابل نوشتن باشد)
     * @param string $prefix    پیشوند کلیدها
     *
     * @throws StoreUnavailableException اگر مسیر قابل نوشتن نباشد
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = '',
    ) {
        $this->assertDirectoryWritable();
    }

    // ─── API عمومی ───────────────────────────────────────────────────────

    public function get(string $key): mixed
    {
        // …
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        // …
    }

    // ─── متدهای خصوصی ────────────────────────────────────────────────────

    private function path(string $key): string
    {
        // …
    }

    private function assertDirectoryWritable(): void
    {
        // …
    }
}
```

### ۱٫۱ قواعد 🔒
| # | قاعده |
|---|---|
| 1 | `<?php` در خط ۱ بدون فاصله/BOM |
| 2 | خط خالی، سپس `declare(strict_types=1);` |
| 3 | خط خالی، سپس بلوک هدر (اختیاری برای کلاس‌های کوچک) یا مستقیماً PHPDoc |
| 4 | `namespace` بدون تورفتگی |
| 5 | خط خالی، سپس `use` ها (الفبایی، بدون گروه‌بندی خالی) |
| 6 | خط خالی، سپس PHPDoc کلاس |
| 7 | `final` / `abstract` / `readonly` به‌ترتیب صحیح |
| 8 | اعضای کلاس طبق DOC-011 §2 |
| 9 | جداکنندهٔ بخش‌ها: کامنت `// ─── <نام بخش> ───` |
| 10 | بدون `?>` پایانی |

---

## ۲. الگوی فایل Interface

```php
<?php
declare(strict_types=1);

namespace Tavaan\Core\Contracts\Cache;

/**
 * قرارداد درایورهای حافظهٔ نهان.
 *
 * پیاده‌سازی‌ها باید Thread/Fiber-Safe باشند و هیچ State جهانی نداشته باشند.
 *
 * @api 1.0
 * @implementors FileStore, RedisStore, ArrayStore, ApcuStore, DatabaseStore, MemcachedStore
 */
interface StoreInterface
{
    /**
     * @return mixed مقدار یا `null` در صورت نبود/انقضا
     */
    public function get(string $key): mixed;

    /**
     * @param int $ttl ثانیه؛ `0` = بدون انقضا
     * @return bool موفقیت
     */
    public function put(string $key, mixed $value, int $ttl = 0): bool;

    public function forget(string $key): bool;
    public function flush(): bool;
    public function getPrefix(): string;
}
```

🔒 Interface **هرگز** نباید Constant عمومی دامنه‌ای داشته باشد (فقط فنی).
🔒 تعداد متدها ≤ ۸؛ بیشتر → تجزیه به Interfaceهای کوچک‌تر.

---

## ۳. الگوی فایل Enum

```php
<?php
declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\I18n\TranslatableEnum;

/**
 * وضعیت یک Job در صف.
 *
 * @api 1.0
 */
enum JobStatus: string implements TranslatableEnum
{
    case Pending    = 'pending';
    case Reserved   = 'reserved';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Dead       = 'dead';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Dead => true,
            default => false,
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending    => $to === self::Reserved,
            self::Reserved   => $to === self::Processing || $to === self::Pending,
            self::Processing => $to === self::Completed || $to === self::Failed,
            self::Failed     => $to === self::Pending || $to === self::Dead,
            self::Completed, self::Dead => false,
        };
    }

    public function translationKey(): string
    {
        return 'queue.job.status.' . $this->value;
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Reserved, self::Processing];
    }
}
```

🔒 هر Enum باید `translationKey()` داشته باشد (i18n).
🔒 هر Enum وضعیتی باید `canTransitionTo()` داشته باشد (جلوگیری از گذار نامجاز).

---

## ۴. الگوی فایل Config

```php
<?php
declare(strict_types=1);

/**
 * پیکربندی موتور Cache.
 *
 * @see docs/03-engines/cache.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | درایور پیش‌فرض
    |--------------------------------------------------------------------------
    | مقدارهای مجاز: array, file, redis, memcached, apcu, database
    */
    'default' => env_string('TVN_CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | پیشوند کلیدها
    |--------------------------------------------------------------------------
    */
    'prefix' => env_string('TVN_CACHE_PREFIX', 'tvn:'),

    /*
    |--------------------------------------------------------------------------
    | فروشگاه‌ها
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'array' => [
            'driver'    => 'array',
            'serialize' => false,
            'limit'     => 10_000,      // حداکثر تعداد کلید (جلوگیری از leak)
        ],

        'file' => [
            'driver'     => 'file',
            'path'       => storage_path('cache/data'),
            'permission' => 0664,
            'shards'     => 256,        // تعداد زیرپوشه برای توزیع
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => env_string('TVN_REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => 'default',
            'serialize'  => 'igbinary',  // igbinary|php|json  (fallback: json)
        ],

        'database' => [
            'driver'     => 'database',
            'connection' => env_string('TVN_DB_CONNECTION', null),
            'table'      => 'cache',
            'key_column' => 'key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | قابلیت‌ها
    |--------------------------------------------------------------------------
    */
    'tags' => [
        'enabled' => true,
        'separator' => ':',
    ],

    'lock' => [
        'driver'  => env_string('TVN_CACHE_LOCK_DRIVER', 'file'),
        'timeout' => 5,        // ثانیه
        'retry'   => 100,      // میلی‌ثانیه بین تلاش‌ها
    ],

    'stampede_protection' => [
        'enabled'   => true,
        'lock_ttl'  => 10,
        'serve_stale' => true,   // ارائهٔ مقدار منقضی در زمان بازسازی
    ],
];
```

🔒 فایل Config فقط `return []` — هیچ منطق، هیچ side effect.
🔒 `env()` فقط در این فایل‌ها.
🔒 هر کلید با کامنت توضیحی + مقدارهای مجاز.
🔒 همهٔ مقادیر باید **پیش‌فرض منطقی** داشته باشند (بدون `.env` هم کار کند).

---

## ۵. الگوی فایل Route

```php
<?php
declare(strict_types=1);

/**
 * روت‌های وب ماژول فروشگاه.
 *
 * @see docs/05-applications/shop.md
 */

use Modules\Shop\Http\Controllers\CartController;
use Modules\Shop\Http\Controllers\CheckoutController;
use Modules\Shop\Http\Controllers\ProductController;
use Modules\Shop\Http\Middleware\EnsureShopIsEnabled;
use Tavaan\Core\Facade\Facades\Route;

Route::middleware(['web', EnsureShopIsEnabled::class])
    ->prefix('shop')
    ->name('shop.')
    ->group(function (): void {

        // ── عمومی ────────────────────────────────────────────────────
        Route::get('/products', [ProductController::class, 'index'])
            ->name('product.index')
            ->cache(seconds: 60, tags: ['shop.products']);

        Route::get('/products/{product:slug}', [ProductController::class, 'show'])
            ->name('product.show')
            ->where('product', '[a-z0-9\-]{2,120}');

        // ── سبد خرید (نیاز به auth) ──────────────────────────────────
        Route::middleware('auth')->group(function (): void {
            Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
            Route::post('/cart/items', [CartController::class, 'add'])
                ->name('cart.add')
                ->throttle('30,1');            // ۳۰ بار در دقیقه
            Route::delete('/cart/items/{item}', [CartController::class, 'remove'])
                ->name('cart.remove');

            Route::get('/checkout', [CheckoutController::class, 'form'])->name('checkout.form');
            Route::post('/checkout', [CheckoutController::class, 'submit'])
                ->name('checkout.submit')
                ->throttle('5,1')
                ->idempotent();                // 🔑 محافظت از ارسال دوباره
        });
    });

// ── پنل مدیریت ───────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'can:shop.product.manage'])
    ->prefix('admin/shop')
    ->name('shop.admin.')
    ->group(base_path('modules/shop/routes/admin.php'));
```

🔒 فایل Route فقط تعریف روت — بدون منطق.
🔒 همیشه `->name()` داشته باشد (برای `route()` و Permission).
🔒 همیشه `->where()` برای پارامترها.
🔒 گروه‌بندی با Middleware مشترک.

---

## ۶. الگوی فایل View (`.tv.php`)

```php
<?php /** @var \Modules\Shop\Domain\Entities\Product $product */ ?>
@extends('layouts.app')

@section('title', $product->title . ' | ' . config('app.name'))
@section('meta_description', e($product->summary))

@section('content')
    <article class="product" itemscope itemtype="https://schema.org/Product">
        <h1 itemprop="name">{{ $product->title }}</h1>

        @if ($product->hasDiscount())
            <x-alert type="success" :dismissible="true">
                {{ trans('shop.product.discount', ['percent' => $product->discountPercent()]) }}
            </x-alert>
        @endif

        <div class="product__price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <span itemprop="price" content="{{ $product->price->amount() }}">
                {{ money($product->price) }}
            </span>
            <meta itemprop="priceCurrency" content="{{ $product->price->currency()->value }}">
        </div>

        {{-- ⚠️ فقط برای محتوای trusts شدهٔ ادمین --}}
        <div class="product__description">{!! $product->descriptionHtml() !!}</div>

        @can('shop.product.update', $product)
            <a href="{{ route('shop.admin.product.edit', $product) }}" class="btn">
                {{ trans('shop.product.edit') }}
            </a>
        @endcan

        @module('shop:reviews/list', ['product' => $product->id()])
    </article>
@endsection

@push('scripts')
    <script src="{{ asset('modules/shop/product.js') }}" defer></script>
@endpush
```

🔒 `{{ }}` همیشه escape؛ `{!! !!}` فقط با کامنت دلیل.
🔒 هیچ منطق کسب‌وکار در View (فقط نمایش و شرط ساده).
🔒 هر متغیر با `/** @var */` برای IDE.
🔒 استفاده از `trans()` نه رشتهٔ خام فارسی.

---

## ۷. الگوی فایل Migration

```php
<?php
declare(strict_types=1);

use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Core\Facade\Facades\Schema;

/**
 * ساخت جدول محصولات فروشگاه.
 *
 * @module tavaan.shop
 * @see docs/05-applications/shop.md
 */
return new class extends Migration
{
    protected string $connection = 'mysql';

    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slug', 160)->unique();
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->decimal('price', 18, 4)->default(0);
            $table->char('currency', 3)->default('IRR');
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps(3);
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'idx_products_status_created');
            $table->index(['category_id', 'status'], 'idx_products_cat_status');
            $table->fullText(['title', 'summary'], 'ft_products_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
```

🔒 `down()` اجباری (مگر با ADR).
🔒 Anonymous Class (بدون نام کلاس تکراری).
🔒 نام فایل: `YYYY_MM_DD_HHMMSS_<verb>_<table>.php`.
🔒 ایندکس‌ها در همان migration تعریف شوند.
🔒 بدون `TRUNCATE`/`DROP` داده در migration داده‌ای بدون Backup.

---

## ۸. الگوی فایل Test

→ طبق DOC-011 §8

```php
<?php
declare(strict_types=1);

namespace Tavaan\Tests\Unit\Engine\Cache;

use Tavaan\Engine\Cache\Drivers\FileStore;
use Tavaan\Testing\TestCase;

/**
 * @covers \Tavaan\Engine\Cache\Drivers\FileStore
 * @group cache, unit
 */
final class FileStoreTest extends TestCase
{
    private FileStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new FileStore($this->tempDir(), prefix: 'test:');
    }

    protected function tearDown(): void
    {
        $this->store->flush();
        parent::tearDown();
    }

    public function testPut_Get_Roundtrip(): void
    {
        $this->store->put('k', ['a' => 1, 'b' => [2, 3]], ttl: 60);
        self::assertSame(['a' => 1, 'b' => [2, 3]], $this->store->get('k'));
    }

    public function testGet_AfterTtl_ReturnsNull(): void
    {
        $this->store->put('k', 'v', ttl: 1);
        $this->travel(2)->seconds();          // Clock Fake
        self::assertNull($this->store->get('k'));
    }

    public function testPut_WithUnwritableDir_Throws(): void
    {
        $this->expectException(\Tavaan\Core\Exceptions\Cache\StoreUnavailableException::class);
        new FileStore('/proc/nonexistent');
    }
}
```

---

## ۹. الگوی فایل Lang

```php
<?php
// modules/shop/lang/fa/shop.php
declare(strict_types=1);

return [
    'product' => [
        'title'      => 'عنوان محصول',
        'price'      => 'قیمت',
        'discount'   => ':percent٪ تخفیف ویژه',
        'edit'       => 'ویرایش محصول',
        'not_found'  => 'محصول مورد نظر یافت نشد.',
        'out_of_stock' => 'موجودی این محصول به پایان رسیده است.',
    ],
    'order' => [
        'status' => [
            'draft'     => 'پیش‌نویس',
            'pending'   => 'در انتظار پرداخت',
            'paid'      => 'پرداخت‌شده',
            'shipping'  => 'در حال ارسال',
            'delivered' => 'تحویل شده',
            'canceled'  => 'لغو شده',
            'refunded'  => 'مسترد شده',
        ],
    ],
    'cart' => [
        'empty'        => 'سبد خرید شما خالی است.',
        'too_many_items' => 'حداکثر تعداد اقلام مجاز :max است.',
    ],
];
```
🔒 ساختار آینه‌ای در همهٔ localeها (`fa` و `en` باید کلیدهای یکسان داشته باشند).
🔒 تست خودکار: `tvn lang:check` → کلیدهای گمشده/اضافی.
🔒 Placeholder با `:name`.

---

## ۱۰. الگوی فایل `module.json` / `plugin.json`
→ طبق DOC-022 §3

## ۱۱. الگوی فایل مستند (`docs/**/*.md`)
→ طبق DOC-015

---

## ۱۲. قرارداد فایل‌های غیر‌PHP

| فایل | قالب | قاعده |
|---|---|---|
| `.env.example` | INI-like | همهٔ کلیدها با مقدار پیش‌فرض + کامنت |
| `.editorconfig` | INI | `indent_style=space, indent_size=4, charset=utf-8, end_of_line=lf, insert_final_newline=true, trim_trailing_whitespace=true, max_line_length=120` |
| `.gitignore` | — | طبق DOC-080 §9 |
| `*.json` | JSON | بدون کامنت؛ با `$schema` |
| `*.yaml/yml` | YAML | فقط برای CI/Deploy (نه Config اجرا) |
| `*.stub` | Template | با `{{placeholder}}` و `{{namespace}}` |
| `*.bench.php` | PHP | خروجی جدول + JSON |

### ۱۲٫۱ `.editorconfig`
```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 4

[*.{json,yml,yaml,md,css,js,ts}]
indent_size = 2

[*.md]
trim_trailing_whitespace = false

[Makefile]
indent_style = tab
```

---

## ۱۳. قرارداد `.stub` (برای Scaffold)

```stub
<?php
declare(strict_types=1);

namespace {{namespace}};

use {{baseClass}};

/**
 * {{description}}
 *
 * @api 1.0
 */
final class {{class}} extends {{baseClassShort}}
{
{{#each methods}}
    public function {{name}}({{params}}): {{return}}
    {
        // TODO: implement
    }

{{/each}}
}
```
Placeholderها: `{{var}}`, `{{#each}}`, `{{#if}}`, `{{> partial}}`.
🔒 موتور `scaffold` یک Template Engine ساده برای `.stub` دارد (بدون Handlebars خارجی).
