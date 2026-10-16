<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — نمونهٔ CRUD کامل (خروجی کلیدی M3 — DOC-120).
 *
 * زنجیرهٔ کامل: Schema → Migration-free setup → ORM (Active Record) →
 * Validation (با قواعد ایرانی) → Pagination → Query Builder خام.
 * همه روی SQLite in-memory (بدون هیچ وابستگی خارجی).
 *
 * اجرا: php examples/crud/blog-crud.php
 */

use Tavaan\Core\Application\ApplicationBuilder;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\ModelQueryBuilder;
use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Core\Facade\Facades\Validator;

require \dirname(__DIR__, 2) . '/core/bootstrap.php';

$app = ApplicationBuilder::create(TVN_BASE_PATH)->forConsole()->boot();
$app->make('config')->set('database.default', 'sqlite_memory');

// ─── ۱. Schema: ساخت جداول ────────────────────────────────────────────────

Schema::create('categories', function (Blueprint $t): void {
    $t->ulid('id')->primary();
    $t->string('title', 100);
    $t->string('slug')->unique();
    $t->timestamps(3);
});

Schema::create('posts', function (Blueprint $t): void {
    $t->ulid('id')->primary();
    $t->foreignUlid('category_id')->nullable();
    $t->string('title', 190);
    $t->string('slug')->unique();
    $t->text('body')->nullable();
    $t->string('status', 20)->default('draft');
    $t->integer('price')->default(0);          // پول = int minor-unit (ADR-0013)
    $t->unsignedInteger('views')->default(0);
    $t->timestamp('published_at', 3)->nullable();
    $t->timestamps(3);
    $t->softDeletes();
    $t->index(['status', 'published_at'], 'idx_posts_status');
    $t->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
});

echo "✓ جداول ساخته شدند\n";

// ─── ۲. مدل‌ها (Active Record) ──────────────────────────────────────────────

final class Category extends Model
{
    protected string $table = 'categories';

    /** @var list<string> */
    protected array $fillable = ['title', 'slug'];

    public function posts(): \Tavaan\Engine\Database\ORM\Relations\HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }
}

final class Post extends Model
{
    protected string $table = 'posts';

    public bool $softDeletes = true;

    /** @var list<string> */
    protected array $fillable = ['category_id', 'title', 'slug', 'body', 'status', 'price', 'published_at'];

    /** @var array<string, string> */
    protected array $casts = [
        'price' => 'money:IRR',
        'views' => 'int',
        'published_at' => 'immutable_datetime',
    ];

    public function category(): \Tavaan\Engine\Database\ORM\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function scopePublished(ModelQueryBuilder $q): ModelQueryBuilder
    {
        $q->where('status', 'published');

        return $q;
    }
}

// ─── ۳. Create با Validation ───────────────────────────────────────────────

$rules = [
    'title' => ['required', 'string', 'max:190'],
    'slug' => ['required', 'slug', 'unique:posts,slug'],
    'body' => ['nullable', 'string', 'min:10'],
    'price' => ['required', 'money'],
    'status' => ['required', 'in:draft,published'],
];

$input = [
    'title' => 'آموزش فریمورک تَوان',
    'slug' => 'tavaan-tutorial',
    'body' => 'در این مقاله با فریمورک فارسی‌اول تَوان آشنا می‌شویم؛ از Query Builder تا ORM و اعتبارسنجی با قواعد ایرانی.',
    'price' => 1_500_000,   // ریال — int minor-unit
    'status' => 'published',
];

$validated = Validator::validate($input, $rules, attributes: ['title' => 'عنوان', 'price' => 'قیمت']);

$category = Category::create(['title' => 'آموزش', 'slug' => 'amoozesh']);
$post = Post::create([...$validated, 'category_id' => $category->getKey(), 'published_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))]);

echo "✓ Create — شناسهٔ ULID: {$post->getKey()}\n";
echo '  قیمت (cast پول): ' . $post->price->format('fa') . "\n";

// Validation رد‌شده (slug تکراری + قیمت منفی)
$bad = Validator::make(['title' => 'x', 'slug' => 'tavaan-tutorial', 'body' => null, 'price' => -5, 'status' => 'nope'], $rules);
if ($bad->fails()) {
    echo "✓ Validation — خطاها: " . \implode(' | ', $bad->errors()->all()) . "\n";
}

// ─── ۴. Read — Query Builder + روابط + eager ──────────────────────────────

for ($i = 1; $i <= 12; $i++) {
    Post::create([
        'title' => 'پست ' . $i,
        'slug' => 'post-' . $i,
        'body' => 'محتوای پست شمارهٔ ' . $i . ' برای نمونهٔ CRUD با صفحه‌بندی.',
        'status' => $i % 3 === 0 ? 'draft' : 'published',
        'price' => $i * 100_000,
        'category_id' => $category->getKey(),
    ]);
}

$count = Post::published()->count();
$withCategory = Post::with('category')->published()->orderByDesc('created_at')->get();
echo "✓ Read — {$count} پست منتشرشده (۲ کوئری با eager loading، N+1=۰)\n";
echo '  دستهٔ اولین پست: ' . $withCategory[0]->category->title . "\n";

// Query Builder خام (join + aggregate)
$rows = $app->make('db')->table('posts as p')
    ->join('categories as c', 'c.id', '=', 'p.category_id')
    ->selectRaw('c.title, COUNT(*) as cnt, SUM(p.price) as total')
    ->where('p.status', 'published')
    ->groupBy('c.title')
    ->get();
echo '  Aggregate خام: ' . \json_encode($rows, JSON_UNESCAPED_UNICODE) . "\n";

// ─── ۵. Pagination ─────────────────────────────────────────────────────────

$paginator = Post::orderBy('title')->paginate(perPage: 5, page: 2);
echo "✓ Pagination — صفحهٔ {$paginator->currentPage()} از {$paginator->lastPage()} (کل: {$paginator->total()})\n";

$cursor = Post::orderBy('title')->cursorPaginate(perPage: 5);
echo '  Cursor — صفحهٔ بعد دارد؟ ' . ($cursor->hasMorePages() ? 'بله' : 'خیر') . "\n";

// ─── ۶. Update + Soft Delete + Restore ────────────────────────────────────

$post->update(['status' => 'published', 'price' => 1_750_000]);
assert($post->price instanceof Money && $post->price->amount === 1_750_000);
echo "✓ Update — قیمت جدید: {$post->price->format('fa')}\n";

$post->delete();                                  // soft delete
echo '✓ Soft Delete — یافتن با دامنهٔ پیش‌فرض: ' . (Post::find($post->getKey()) === null ? 'null (درست)' : '!!') . "\n";
echo '  با withTrashed: ' . (Post::withTrashed()->find($post->getKey()) !== null ? 'یافت شد' : '!!') . "\n";

$post->restore();
echo "✓ Restore — بازگشت: " . (Post::find($post->getKey()) !== null ? 'موفق' : '!!') . "\n";

// ─── ۷. Transaction ────────────────────────────────────────────────────────

$db = $app->make('db')->connection();
try {
    $db->transaction(static function () use ($category): void {
        Post::create(['title' => 'تراکنشی', 'slug' => 'tx-post', 'price' => 10, 'status' => 'draft', 'category_id' => $category->getKey()]);
        throw new RuntimeException('rollback-demo');
    });
} catch (RuntimeException) {
    // انتظار
}
echo '✓ Transaction — رکورد rollback شد؟ ' . (Post::withTrashed()->where('slug', 'tx-post')->count() === 0 ? 'بله' : 'خیر!!') . "\n";

echo "\n🎉 CRUD کامل M3 روی دیتابیس انجام شد.\n";
