# DOC-151 — قالب ماژول (Module Template)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-151 |
| نقش | الگوی ساخت یک ماژول دامنه‌ای جدید |
| مرتبط | DOC-022، DOC-021، DOC-080 |

---

## ۱. ساختار پوشه (کپی این الگو)

```
modules/<kebab-name>/
├── module.json                       ← Manifest (بخش ۲)
├── composer.json                     ← اختیاری (فقط انتشار)
├── README.md                         ← راهنمای کوتاه
├── src/
│   ├── Contracts/                    ← 🔓 تنها سطح عمومی
│   │   ├── <Name>ReaderInterface.php
│   │   ├── Dtos/
│   │   └── Events/                   ← رویدادهای عمومی
│   ├── Domain/
│   │   ├── Entities/                 ← Aggregate Root + Entity
│   │   ├── ValueObjects/
│   │   ├── Services/                 ← Domain Service
│   │   ├── Events/                   ← Domain Event (داخلی)
│   │   ├── Exceptions/
│   │   ├── Policies/
│   │   └── Repositories/             ← 🔑 Interface فقط
│   ├── Application/
│   │   ├── UseCases/
│   │   ├── Commands/
│   │   ├── Queries/
│   │   ├── Handlers/
│   │   ├── Dtos/
│   │   └── Listeners/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Controllers/Admin/
│   │   ├── Middleware/
│   │   ├── Requests/                 ← FormRequest
│   │   ├── Resources/                ← API Resource
│   │   └── Widgets/                  ← HMVC
│   ├── Console/Commands/
│   ├── Infrastructure/
│   │   ├── Persistence/              ← Repository Impl + Mapper
│   │   ├── Cache/
│   │   ├── Search/
│   │   └── Gateways/
│   ├── Providers/
│   │   ├── <Name>ServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── EventServiceProvider.php
│   └── Support/
├── config/<name>.php
├── routes/
│   ├── web.php
│   ├── api.php
│   ├── admin.php
│   └── console.php
├── views/                            ← namespace: <name>::
│   ├── front/
│   ├── admin/
│   ├── widgets/
│   └── mails/
├── lang/{fa,en}/<name>.php
├── migrations/
├── seeds/
├── tests/{Unit,Feature,Benchmark}/
└── docs/
    ├── README.md
    ├── domain-model.md
    └── api.md
```

---

## ۲. الگوی `module.json`

```json
{
  "$schema": "https://docs.tavaan.dev/schemas/module.v1.json",
  "id": "tavaan.<name>",
  "name": "<Name>",
  "title_fa": "<نام فارسی>",
  "title_en": "<English Name>",
  "version": "1.0.0",
  "type": "module",
  "description": "<توضیح یک‌خطی>",
  "orm_style": "repository",
  "license": "MIT",

  "requires": {
    "tavaan": ">=1.0.0",
    "php": ">=8.2.0",
    "engines": ["http","router","database","orm","validation","auth","rbac","cache","event","view","i18n"],
    "modules": ["tavaan.core-user"]
  },

  "autoload": {"psr-4": {"Modules\\<Name>\\": "src/"}},

  "providers": [
    "Modules\\<Name>\\Providers\\<Name>ServiceProvider",
    "Modules\\<Name>\\Providers\\RouteServiceProvider",
    "Modules\\<Name>\\Providers\\EventServiceProvider"
  ],

  "priority": 100,

  "routes": {
    "web": "routes/web.php",
    "api": "routes/api.php",
    "admin": "routes/admin.php",
    "console": "routes/console.php"
  },

  "views": {"namespace": "<name>", "path": "views"},
  "lang": {"path": "lang", "namespaces": {"<name>": "lang"}},
  "migrations": {"path": "migrations"},
  "seeds": {"path": "seeds", "classes": ["<Name>DatabaseSeeder"]},
  "config": {"path": "config", "merge": true},
  "assets": {"path": "public", "publish_to": "modules/<name>"},

  "widgets": {},
  "commands": [],
  "jobs": [],

  "events": {
    "published": [
      {"name": "<name>.<aggregate>.<verb>", "class": "Modules\\<Name>\\Contracts\\Events\\<Event>", "version": 1}
    ],
    "subscribed": []
  },

  "permissions": {
    "group": {"id": "<name>", "title_fa": "<نام فارسی>"},
    "items": [
      {"key": "<name>.<resource>.view", "title_fa": "مشاهدهٔ <منبع>"},
      {"key": "<name>.<resource>.create", "title_fa": "ایجاد <منبع>"},
      {"key": "<name>.<resource>.update", "title_fa": "ویرایش <منبع>"},
      {"key": "<name>.<resource>.delete", "title_fa": "حذف <منبع>"}
    ]
  },

  "roles": [],
  "navigation": [],
  "settings": {"group": "<name>", "items": []},

  "database": {
    "table_prefix": "<name>_",
    "tables": ["<name>_<resource>s"],
    "seed_on_install": true
  },

  "health": {"endpoint": "/health/<name>", "checks": ["db","cache"]},

  "extractable": {
    "enabled": true,
    "service_name": "<name>-service",
    "api_prefix": "/api/v1/<name>",
    "events_transport": "outbox"
  },

  "install": {
    "steps": ["migrate", "seed", "publish:assets", "cache:clear"]
  }
}
```

---

## ۳. الگوی ServiceProvider

```php
<?php
declare(strict_types=1);

namespace Modules\<Name>\Providers;

use Modules\<Name>\Domain\Repositories\<Entity>RepositoryInterface;
use Modules\<Name>\Infrastructure\Persistence\Database<Entity>Repository;
use Tavaan\Core\Provider\ServiceProvider;

final class <Name>ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 🔒 فقط Binding — بدون side effect
        $this->app->singleton(
            <Entity>RepositoryInterface::class,
            Database<Entity>Repository::class,
        );
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/<name>.php', '<name>');
        $this->loadViewsFrom(__DIR__ . '/../../views', '<name>');
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', '<name>');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([/* … */]);
        }
    }
}
```

---

## ۴. الگوی Domain Entity (حالت Repository)

```php
<?php
declare(strict_types=1);

namespace Modules\<Name>\Domain\Entities;

use Modules\<Name>\Domain\Events\<Entity>WasCreated;
use Tavaan\Support\Id\Ulid;

final class <Entity> extends AggregateRoot
{
    private function __construct(
        private readonly string $id,
        private string $title,
        private <Entity>Status $status,
    ) {}

    public static function create(string $title): self
    {
        if (\trim($title) === '') {
            throw new EmptyTitleException(messageId: '<name>.title.required');
        }
        $entity = new self(Ulid::generate()->toString(), $title, <Entity>Status::Draft);
        $entity->recordThat(<Entity>WasCreated::of($entity));
        return $entity;
    }

    public function id(): string { return $this->id; }
    public function title(): string { return $this->title; }
    public function status(): <Entity>Status { return $this->status; }

    public function rename(string $title): void
    {
        $this->title = $title;
        $this->recordThat(<Entity>WasRenamed::of($this->id, $title));
    }
}
```

---

## ۵. الگوی UseCase (Application)

```php
<?php
declare(strict_types=1);

namespace Modules\<Name>\Application\UseCases;

final class Create<Entity>UseCase
{
    public function __construct(
        private readonly <Entity>RepositoryInterface $repository,
        private readonly TransactionManagerInterface $tx,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function execute(Create<Entity>Dto $dto): <Entity>
    {
        return $this->tx->run(function () use ($dto): <Entity> {
            $entity = <Entity>::create($dto->title);
            $this->repository->save($entity);
            foreach ($entity->pullRecordedEvents() as $event) {
                $this->events->dispatch($event);
            }
            return $entity;
        });
    }
}
```

---

## ۶. الگوی Controller (Presentation)

```php
<?php
declare(strict_types=1);

namespace Modules\<Name>\Http\Controllers;

use Modules\<Name>\Application\UseCases\Create<Entity>UseCase;
use Modules\<Name>\Http\Requests\Store<Entity>Request;
use Modules\<Name>\Http\Resources\<Entity>Resource;
use Tavaan\Engine\Http\Controller;
use Tavaan\Engine\Http\JsonResponse;

final class <Entity>Controller extends Controller
{
    public function __construct(private readonly Create<Entity>UseCase $create<Entity>) {}

    public function store(Store<Entity>Request $request): JsonResponse
    {
        $entity = $this->create<Entity>->execute($request->toDto());
        return <Entity>Resource::make($entity)->withStatus(201)->toResponse();
    }
}
```

---

## ۷. الگوی Routes

```php
<?php
declare(strict_types=1);

use Modules\<Name>\Http\Controllers\<Entity>Controller;
use Tavaan\Core\Facade\Facades\Route;

Route::middleware(['web'])
    ->prefix('<name>')
    ->name('<name>.')
    ->group(function (): void {
        Route::get('/<resource>s', [<Entity>Controller::class, 'index'])->name('<resource>.index');
        Route::post('/<resource>s', [<Entity>Controller::class, 'store'])->name('<resource>.store')->middleware('auth');
    });
```

---

## ۸. الگوی تست

```php
<?php
declare(strict_types=1);

namespace Modules\<Name>\Tests\Feature;

use Modules\<Name>\Domain\Entities\<Entity>;
use Tavaan\Testing\TestCase;

final class Create<Entity>Test extends TestCase
{
    use RefreshDatabase;

    public function testCreate<Entity>_WithValidData_Persists(): void
    {
        $response = $this->actingAs($this->userWithPermission('<name>.<resource>.create'))
            ->postJson('/api/v1/<name>/<resource>s', ['title' => 'نمونه']);

        $response->assertCreated();
        $this->assertDatabaseHas('<name>_<resource>s', ['title' => 'نمونه']);
    }

    public function testCreate<Entity>_WithoutPermission_Forbidden(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/<name>/<resource>s', ['title' => 'x'])
            ->assertForbidden();
    }
}
```

---

## ۹. چک‌لیست ساخت ماژول 🧪

- [ ] `module.json` معتبر (`tvn module:validate tavaan.<name>`)
- [ ] ساختار پوشه مطابق §1
- [ ] Contracts در `src/Contracts/` (تنها سطح عمومی)
- [ ] Domain بدون وابستگی به Engine/Infrastructure
- [ ] Repository Interface در Domain، Impl در Infrastructure
- [ ] UseCase با Transaction Boundary
- [ ] Controller فقط ترجمه
- [ ] Routes با name + middleware
- [ ] FormRequest برای validation
- [ ] Policy برای authorization
- [ ] مجوزها در manifest
- [ ] رویدادهای عمومی با Schema
- [ ] Migration با `down()`
- [ ] Seeder
- [ ] Viewها با namespace
- [ ] lang fa + en
- [ ] تست Unit + Feature
- [ ] `tvn arch:check` بدون نقض مرز
- [ ] README + docs

---

## ۱۰. تولید خودکار

```bash
tvn make:module <name> --domain=hexagonal
```
→ موتور `scaffold` (DOC-077) این ساختار را تولید می‌کند.
