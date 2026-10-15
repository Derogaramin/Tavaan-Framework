# DOC-050 — موتور ORM | نگاشت شیء-رابطه

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-050 |
| Engine ID | `tavaan.engine.orm` |
| Namespace | `Tavaan\Engine\Database\ORM` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `database`, `event`, `i18n` |
| Facade | `Model` (base class) |
| LOC | ۴۰۰۰ |

---

## ۱. هدف

لایهٔ ORM با **دو الگوی همزمان**: Active Record (ساده) و Repository + Domain Object (تمیز)،
با رابطه‌ها، Eager/Lazy Loading، Cast، Scope، Observer، Soft Delete و رویدادهای مدل.

---

## ۲. دو حالت ORM

| حالت | استفاده | مزیت | عیب |
|---|---|---|---|
| **Active Record** | `mvc`, پروژه کوچک | سریع، ساده | دامنه به DB چسبیده |
| **Repository + PDO** | `hexagonal`, ماژول قابل استخراج | دامنهٔ تمیز، تست‌پذیر | کد بیشتر |

```php
// Active Record
$user = User::find(1);
$user->name = 'x'; $user->save();

// Repository
$user = $this->users->findById($id);   // returns Domain Entity
$this->users->save($user);             // Mapper در Infrastructure
```
🔒 یک ماژول باید **یک حالت** را انتخاب کند (در `module.json` → `orm_style`).

---

## ۳. Active Record — API

```php
final class Product extends Model
{
    protected string $table = 'shop_products';
    protected string $primaryKey = 'id';
    protected string $keyType = 'ulid';                 // ulid|bigint|uuid
    public bool $incrementing = false;
    public bool $timestamps = true;
    public bool $softDeletes = true;

    /** @var list<string> */
    protected array $fillable = ['title','slug','price','stock','status','category_id'];
    protected array $guarded = ['id','created_by'];
    protected array $hidden = ['internal_note'];
    protected array $appends = ['price_formatted'];
    protected array $casts = [
        'price'      => 'money:IRR',
        'attributes' => 'array',
        'status'     => ProductStatus::class,
        'published_at' => 'datetime',
        'is_featured'  => 'bool',
        'secret'     => 'encrypted',
    ];
    protected array $with = ['category'];                // eager پیش‌فرض
    protected array $withCount = ['reviews'];

    // ── Relations ─────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag')->withTimestamps();
    }
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Accessors / Mutators ──────────────────────
    public function getPriceFormattedAttribute(): string
    {
        return money($this->price)->format();
    }
    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slugPersian($value);
    }

    // ── Scopes ────────────────────────────────────
    public function scopeActive(QueryBuilder $q): QueryBuilder
    {
        return $q->where('status', ProductStatus::Active->value);
    }
    public function scopeInStock(QueryBuilder $q): QueryBuilder
    {
        return $q->where('stock', '>', 0);
    }

    // ── Domain behavior ───────────────────────────
    public function applyDiscount(Percentage $p): void
    {
        $this->price = $this->price->multiply($p->inverse());
    }
}
```

### ۳٫۱ متدهای ایستا (Query)
```php
Product::query();                      // QueryBuilder جدید
Product::all();
Product::find(1); Product::findOrFail(1); Product::findMany([1,2]);
Product::where('status','active')->get();
Product::active()->inStock()->orderByDesc('created_at')->paginate(20);
Product::with(['category','tags'])->find($id);
Product::withCount('reviews')->get();
Product::create(['title'=>'x']);       // با fillable
Product::updateOrCreate(['slug'=>$s], ['title'=>$t]);
Product::firstOrCreate([...]);
Product::upsert($rows, ['slug'], ['title']);
Product::count(); Product::sum('price'); Product::pluck('title','id');
Product::chunk(500, fn($c) => /*…*/);
Product::lazy();
```

### ۳٫۲ متدهای نمونه
```php
$product->save();
$product->delete();                    // soft یا hard
$product->restore();
$product->forceDelete();
$product->fill($data);                 // با fillable/guarded
$product->update(['title'=>'y']);
$product->refresh();
$product->isNew(); $product->isDirty(); $product->getChanges();
$product->toArray(); $product->toJson();
$product->replicate();
$product->load('category');            // lazy eager
$product->touch();
```

---

## ۴. رابطه‌ها (Relations)

| نوع | کلاس | مثال |
|---|---|---|
| HasOne | `HasOne` | `hasOne(Profile::class)` |
| HasMany | `HasMany` | `hasMany(Post::class)` |
| BelongsTo | `BelongsTo` | `belongsTo(User::class)` |
| BelongsToMany | `BelongsToMany` | `belongsToMany(Tag::class, 'post_tag')` |
| HasManyThrough | `HasManyThrough` | `hasManyThrough(Post::class, User::class)` |
| HasOneThrough | `HasOneThrough` | — |
| MorphOne | `MorphOne` | `morphOne(Image::class,'imageable')` |
| MorphMany | `MorphMany` | `morphMany(Comment::class,'commentable')` |
| MorphTo | `MorphTo` | `morphTo()` |
| MorphToMany | `MorphToMany` | `morphToMany(Tag::class,'taggable')` |
| MorphPivot | — | — |

### ۴٫۱ Eager Loading (رفع N+1)
```php
// Lazy (N+1)
foreach (Product::all() as $p) { echo $p->category->name; }   // ❌ N کوئری

// Eager
$products = Product::with('category')->get();                  // ✅ ۲ کوئری
foreach ($products as $p) { echo $p->category->name; }

// Nested + Constraint
Product::with(['category' => fn($q) => $q->select('id','name'), 'reviews' => fn($q) => $q->where('approved', true)])->get();

// با شمارش
Product::withCount(['reviews as reviews_count' => fn($q) => $q->where('approved', true)])->get();
```

**الگوریتم Eager Load:**
```
1. اجرای کوئری اصلی → N رکورد والد
2. جمع‌آوری کلیدهای رابطه (parent_key)
3. یک کوئری `WHERE foreign_key IN (…)` برای فرزندان
4. گروه‌بندی فرزندان بر اساس foreign_key (در حافظه)
5. تطبیق (match) فرزندان به والدین
→ به‌جای N+1 کوئری، ۲ کوئری
```

### ۴٫۲ Lazy Loading
```php
// در اولین دسترسی، کوئری اجرا می‌شود
$category = $product->category;         // __get → loadRelation
```
🔒 در `dev` با `n_plus_one_detection` → هشدار اگر در حلقه lazy load شود.

---

## ۵. Castها

| Cast | توضیح |
|---|---|
| `int`, `float`, `string`, `bool`, `array`, `object`, `json` | پایه |
| `datetime`, `date`, `timestamp`, `immutable_datetime` | تاریخ |
| `decimal:2` | Decimal با دقت |
| `money:IRR` | Value Object پول |
| `encrypted` | رمزنگاری‌شده در DB |
| `hashed` | هش خودکار (برای رمز) |
| `ulid`, `uuid` | شناسه |
| `collection` | Collection |
| Enum class-string | `ProductStatus::class` |

```php
protected function casts(): array
{
    return [
        'price' => 'money:' . Currency::IRR->value,
        'status' => ProductStatus::class,          // backed enum
        'published_at' => 'immutable_datetime',
    ];
}

// Attribute-level cast (PHP 8.4 property hooks style با متد)
protected function price(): Cast
{
    return Money::cast(Currency::IRR);
}
```

### ۵٫۱ Cast سفارشی
```php
final class JalaliDateCast implements CastInterface
{
    public function get(Model $model, string $key, mixed $value, array $attrs): ?JalaliDate
    {
        return $value === null ? null : JalaliDate::fromDbString($value);
    }
    public function set(Model $model, string $key, mixed $value, array $attrs): ?string
    {
        return $value instanceof JalaliDate ? $value->toDbString() : $value;
    }
}
// در casts: 'birth_date' => JalaliDateCast::class
```

---

## ۶. Soft Delete، Scope، Observer

```php
// Soft Delete
use SoftDeletes;   // trait
Product::withTrashed()->get();
Product::onlyTrashed()->get();
$p->restore(); $p->forceDelete();
Product::restore(['id' => [1,2]]);

// Global Scope (خودکار روی همهٔ کوئری‌ها)
final class TenantScope implements ScopeInterface
{
    public function apply(QueryBuilder $q, Model $m): void { /* افزودن where */ }
}
protected static function boot(): void
{
    parent::boot();
    static::addGlobalScope(new TenantScope());
}

// Local Scope
Product::active()->get();   // scopeActive

// Observer
final class ProductObserver implements ObserverInterface
{
    public function creating(Product $p): void { $p->created_by = Auth::id(); }
    public function created(Product $p): void  { Search::index($p); }
    public function updating(Product $p): bool { return $this->canEdit($p); }
    public function updated(Product $p): void  { Event::dispatch(new ProductWasUpdated($p->id)); }
    public function deleting(Product $p): void { /* … */ }
    public function deleted(Product $p): void  { Search::delete($p); }
    public function saving(Product $p): void   { /* … */ }
    public function saved(Product $p): void    { /* … */ }
}
Product::observe(ProductObserver::class);
```

### ۶٫۱ رویدادهای مدل
`retrieved, creating, created, updating, updated, saving, saved, deleting, deleted, restoring, restored, replicating`

🔒 رویدادهای مدل **به Domain Event تبدیل** می‌شوند (در Observer یا با `dispatchesEvents`):
```php
protected array $dispatchesEvents = [
    'created' => ProductWasCreated::class,
    'updated' => ProductWasUpdated::class,
];
```

---

## ۷. Repository Pattern (حالت تمیز)

```php
// Domain (Interface)
namespace Modules\Shop\Domain\Repositories;
interface OrderRepositoryInterface
{
    public function findById(OrderId $id): ?Order;
    public function save(Order $order): void;
    public function delete(OrderId $id): void;
    /** @return list<Order> */
    public function findByCustomer(CustomerId $id, int $limit = 50): array;
}

// Infrastructure (Impl)
namespace Modules\Shop\Infrastructure\Persistence;
final class DatabaseOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly OrderMapper $mapper,
    ) {}

    public function findById(OrderId $id): ?Order
    {
        $row = $this->db->table('shop_orders')->where('id', $id->toString())->first();
        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function save(Order $order): void
    {
        $row = $this->mapper->toRow($order);
        $this->db->table('shop_orders')->updateOrInsert(['id' => $order->id()->toString()], $row);
        $this->persistEvents($order->pullRecordedEvents());
    }
}
```
🔒 در این حالت، `Order` یک Domain Object خالص است (بدون `extends Model`).
🔒 Mapper در Infrastructure، Entity در Domain.

---

## ۸. Hydration و Performance

```php
// Hydrate: array row → Model
private function hydrate(array $row): Model
{
    $model = $this->newInstance();
    $model->setRawAttributes($row, sync: true);
    $model->exists = true;
    // اعمال casts به‌صورت lazy (در __get)
    return $model;
}
```

**بهینه‌سازی‌ها:**
| تکنیک | اثر |
|---|---|
| Lazy Cast (در `__get` نه در hydrate) | کاهش حافظه برای رکوردهای بزرگ |
| کش `casts()`/`fillable()` در static | حذف reflection تکراری |
| `select()` فقط ستون‌های لازم | کاهش I/O |
| Eager Loading | حذف N+1 |
| `getDictionary()` با کلید هش | تطبیق سریع رابطه |
| Chunk/Lazy برای bulk | حافظهٔ ثابت |

---

## ۹. امنیت

| تهدید | کنترل |
|---|---|
| Mass Assignment | `fillable`/`guarded` اجباری؛ `Model::unguard()` فقط در seed |
| SQL Injection | از طریق `database` (Prepared) |
| IDOR | Policy در Observer/Controller؛ Row-Level Scope |
| نشت فیلد حساس در JSON | `hidden`/`visible`/`appends` |
| Encrypted Cast برای PII | موبایل/کد ملی رمزنگاری + Blind Index |
| Soft Delete bypass | `withTrashed` نیازمند مجوز |
| N+1 DoS | `n_plus_one_detection` + limit |

```php
public function fill(array $attributes): static
{
    foreach ($attributes as $key => $value) {
        if ($this->isFillable($key)) {
            $this->setAttribute($key, $value);
        } elseif ($this->isGuarded($key)) {
            // 🔒 بی‌صدا رد نکن؛ در dev هشدار بده
            if ($this->strictMode) {
                throw new MassAssignmentException(messageId: 'orm.mass_assignment', context: ['key' => $key]);
            }
        }
    }
    return $this;
}
```

---

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| Hydrate ۱۰۰۰ رکورد | ≤ ۲۵ms |
| `find()` با eager (۲ رابطه) | ≤ ۵ms |
| `save()` (update) | ≤ ۳ms |
| `create()` با insert | ≤ ۴ms |
| Eager load رفع N+1 برای ۱۰۰۰ رکورد | ≤ ۵۰ms (به‌جای ۱۰۰۰ کوئری) |
| حافظهٔ ۱۰۰۰ Model | ≤ ۸MB |

---

## ۱۱. تست
| ID | سناریو |
|---|---|
| T-01 | CRUD کامل با fillable |
| T-02 | Mass Assignment: فیلد guarded → رد |
| T-03 | هر ۱۱ نوع رابطه |
| T-04 | Eager vs Lazy: شمارش کوئری (N+1 رفع شده) |
| T-05 | Cast: money، enum، datetime، encrypted، json |
| T-06 | Accessor/Mutator |
| T-07 | Soft Delete + `withTrashed` |
| T-08 | Global Scope (Tenant) |
| T-09 | Observer: همهٔ رویدادها |
| T-10 | `dispatchesEvents` → Domain Event |
| T-11 | Repository pattern (بدون extends Model) |
| T-12 | `hidden` در `toJson()` |
| T-13 | `updateOrCreate`/`firstOrCreate` |
| T-14 | Hydration ۱۰۰۰ رکورد ≤ ۲۵ms |

---

## ۱۲. معیار پذیرش 🧪
- AC-ORM-01: همهٔ تست‌های §11.
- AC-ORM-02: N+1 در eager loading = ۰ کوئری اضافه.
- AC-ORM-03: کارایی §10.
- AC-ORM-04: هر دو حالت (Active Record + Repository) قابل استفاده.
- AC-ORM-05: Cast سفارشی بدون تغییر هسته.

---

## ۱۳. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Laravel Eloquent | Active Record، رابطه، Cast | افزودن حالت Repository، Cast جلالی/پول داخلی |
| Doctrine ORM | Repository، Mapper | سبک‌تر، بدون Unit of Work کامل (ADR-0007) |
| ActiveRecord (Rails) | Scope، Observer | — |

---

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.orm.v1
engine:
  id: tavaan.engine.orm
  namespace: Tavaan\Engine\Database\ORM
  path: engines/orm
  priority: P0
  phase: M3
  depends_on: [database, event, i18n]
  facade: null
  tables: []
  events_emitted: [model.retrieved, model.created, model.updated, model.deleted, model.saved]
  config_file: engines/orm/config/orm.php
  public_api:
    - class: Model
      methods: [find, findOrFail, all, where, query, create, updateOrCreate, firstOrCreate,
                save, delete, restore, forceDelete, fill, update, refresh, toArray, toJson,
                with, withCount, load, observe, addGlobalScope, chunk, lazy, paginate]
  loc_estimate: 4000
  acceptance:
    - {id: AC-ORM-02, test: 'engines/orm/tests/Feature/EagerLoadingTest.php'}
    - {id: AC-ORM-03, test: 'engines/orm/benchmarks/hydrate.bench.php'}
```
