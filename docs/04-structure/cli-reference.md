# DOC-083 — مرجع کامل CLI (دستورات `tvn`) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-083 |
| وضعیت | Approved v1.0 |
| موتور | `console` (DOC-076)، `scaffold` (DOC-077) |
| نقش | مرجع یکپارچهٔ همهٔ ۱۰۵ دستور `tvn` که در مستندات پراکنده‌اند |
| Entry | `bin/tvn` (یا `bin/tvn.php` در ویندوز) |

---

## ۱. کالبدشناسی دستور

```
tvn <command>[:<subcommand>] [argument] [--option=value] [--flag]

tvn migrate --module=shop --force
    │       │       │        └─ flag (بدون مقدار)
    │       │       └─ option با مقدار
    │       └─ command
    └─ binary (Alias: tavaan)
```

### ۱٫۱ کدهای خروج استاندارد
| کد | نام | معنا |
|---|---|---|
| `0` | SUCCESS | موفق |
| `1` | FAILURE | خطای اجرایی |
| `2` | INVALID | آرگومان/گزینهٔ نامعتبر |

### ۱٫۲ گزینه‌های سراسری (Global Options)
| گزینه | توضیح |
|---|---|
| `--help`, `-h` | راهنمای دستور |
| `--quiet`, `-q` | بدون خروجی |
| `--verbose`, `-v\|-vv\|-vvv` | جزئیات بیشتر |
| `--no-interaction`, `-n` | بدون پرسش (برای CI) |
| `--ansi` / `--no-ansi` | خروجی رنگی |
| `--env=<name>` | محیط (`.env.<name>`) |
| `--json` | خروجی JSON (برای ماشین‌خوانی) |

---

## ۲. فهرست کامل دستورات (۱۰۵ دستور در ۱۶ دسته)

### ۲٫۱ اطلاعات و سلامت (Info & Health)
| دستور | توضیح | موتور | سند |
|---|---|---|---|
| `tvn about` | اطلاعات کامل (نسخه، PHP، env، ماژول‌ها، extensionها) | console | DOC-076 |
| `tvn --version`, `tvn version` | نسخهٔ فریمورک | console | DOC-076 |
| `tvn doctor` | تشخیص سلامت (افزونه‌ها، Fallback، مجوزها، config) | compat | DOC-083E |
| `tvn health:check [--json]` | بررسی liveness/readiness | observability | DOC-067 |
| `tvn env:check` | کلیدهای گمشده نسبت به `.env.example` | config | DOC-032 |

### ۲٫۲ سرویس و اجرا (Serve)
| دستور | توضیح | موتور |
|---|---|---|
| `tvn serve [--host=127.0.0.1] [--port=8000]` | سرور توسعه (Built-in) | http |
| `tvn serve:realtime [--port=8081]` | سرور Realtime (SSE/WebSocket) | realtime |
| `tvn down [--message=] [--retry=]` | حالت تعمیر | http |
| `tvn up` | خروج از حالت تعمیر | http |
| `tvn maintenance:on` / `tvn maintenance:off` | نگهداری | http |

### ۲٫۳ بهینه‌سازی و کش (Optimize)
| دستور | توضیح | موتور | سند |
|---|---|---|---|
| `tvn optimize` | ساخت همهٔ کش‌ها | optimizer | DOC-037 |
| `tvn optimize:clear` | حذف همهٔ کش‌ها | optimizer | DOC-037 |
| `tvn config:cache` / `config:clear` | کش/پاک‌کردن config | optimizer | DOC-032 |
| `tvn route:cache` / `route:clear` | کش/پاک‌کردن روت‌ها | optimizer | DOC-040 |
| `tvn view:cache` / `view:clear` | کامپایل/پاک‌کردن ویوها | optimizer | DOC-043 |
| `tvn event:cache` / `event:clear` | کش Discovery رویدادها | optimizer | DOC-033 |
| `tvn autoload:cache` | تولید ClassMap | optimizer | DOC-027 |
| `tvn container:compile` | کانتینر کامپایل‌شده | optimizer | DOC-026 |
| `tvn preload:generate` | تولید Opcache Preload | optimizer | DOC-027 |
| `tvn assets:build` / `assets:manifest` | build دارایی‌ها | optimizer | DOC-037 |

### ۲٫۴ پایگاه داده (Database & Migration)
| دستور | توضیح | موتور | سند |
|---|---|---|---|
| `tvn migrate [--module=] [--force] [--dry-run]` | اجرای migration | migration | DOC-049 |
| `tvn migrate:fresh` | drop همه + migrate (⚠️ خطرناک) | migration | DOC-049 |
| `tvn migrate:refresh` | rollback همه + migrate | migration | DOC-049 |
| `tvn migrate:rollback [--step=N]` | بازگشت | migration | DOC-049 |
| `tvn migrate:status` | وضعیت migrationها | migration | DOC-049 |
| `tvn migrate:install` | ساخت جدول migrations | migration | DOC-049 |
| `tvn db:seed [--class=]` | اجرای seeder | migration | DOC-047 |
| `tvn db:show` | اطلاعات اتصال/جداول | database | DOC-047 |
| `tvn db:wipe` | حذف همهٔ جداول (⚠️) | database | DOC-047 |
| `tvn db:tenant-guard` | یافتن کوئری‌های بدون tenant scope | tenancy | DOC-082E |
| `tvn schema:diff` | مقایسهٔ مدل با دیتابیس | schema | DOC-048 |

### ۲٫۵ تولید کد (Scaffold — `make:*`)
| دستور | توضیح | سند |
|---|---|---|
| `tvn make:module <name> [--domain=]` | ماژول کامل | DOC-077 |
| `tvn make:engine <name>` | موتور کامل (۷ قلم) | DOC-077 |
| `tvn make:controller <Name> [--resource] [--api]` | کنترلر | DOC-077 |
| `tvn make:model <Name> [--migration --factory --seeder --controller]` | مدل | DOC-077 |
| `tvn make:migration <name> [--module=]` | migration | DOC-077 |
| `tvn make:request <Name>` | FormRequest | DOC-077 |
| `tvn make:policy <Name> [--model=]` | Policy | DOC-077 |
| `tvn make:middleware <Name>` | Middleware | DOC-077 |
| `tvn make:command <Name>` | دستور CLI | DOC-077 |
| `tvn make:job <Name>` | Job صف | DOC-077 |
| `tvn make:event <Name>` | رویداد | DOC-077 |
| `tvn make:listener <Name> [--event=]` | Listener | DOC-077 |
| `tvn make:seeder <Name>` | Seeder | DOC-077 |
| `tvn make:factory <Name>` | Factory | DOC-077 |
| `tvn make:test <Name> [--unit\|--feature]` | تست | DOC-077 |
| `tvn make:widget <Name> [--module=]` | ویجت HMVC | DOC-077 |
| `tvn make:resource <Name> [--module=]` | API Resource | DOC-077 |

> همهٔ `make:*` از `.stub` در `resources/stubs/` استفاده می‌کنند (DOC-077 §4).

### ۲٫۶ ماژول و کیت (Module & Kit)
| دستور | توضیح | سند |
|---|---|---|
| `tvn module:list` | فهرست ماژول‌ها + وضعیت | DOC-038 |
| `tvn module:install <id>` | نصب (migrate+seed+publish+enable) | DOC-038 |
| `tvn module:uninstall <id> [--purge]` | حذف | DOC-038 |
| `tvn module:enable <id>` / `disable <id>` | فعال/غیرفعال | DOC-038 |
| `tvn module:update <id>` | ارتقا | DOC-038 |
| `tvn module:validate <id>` | بررسی manifest + مرزها | DOC-038 |
| `tvn module:deps <id>` | گراف وابستگی | DOC-038 |
| `tvn module:extract <id>` | تولید سرویس مستقل | DOC-024 |
| `tvn module:publish <id> [--with-composer]` | انتشار پکیج | DOC-082 |
| `tvn kit:install <id> [--preset=] [--with-demo]` | نصب کیت | DOC-090 |

### ۲٫۷ موتور و پلاگین (Engine & Plugin)
| دستور | توضیح | سند |
|---|---|---|
| `tvn engine:validate <name>` | بررسی `module.json` موتور | DOC-030 |
| `tvn engine:publish <name> [--with-composer]` | انتشار موتور | DOC-082 |
| `tvn plugin:scan` | اسکن توابع خطرناک پلاگین | DOC-079 |

### ۲٫۸ مسیریابی (Routing)
| دستور | توضیح | سند |
|---|---|---|
| `tvn route:list [--method=] [--path=] [--name=] [--json]` | فهرست روت‌ها | DOC-040 |
| `tvn route:match <METHOD> <path>` | دیباگ تطبیق | DOC-040 |
| `tvn route:tree` | نمایش Trie | DOC-040 |
| `tvn route:check` | تشخیص collision/ReDoS | DOC-040 |
| `tvn route:cache` / `route:clear` | (در §2.3) | DOC-040 |

### ۲٫۹ صف و زمان‌بندی (Queue & Schedule)
| دستور | توضیح | سند |
|---|---|---|
| `tvn queue:work [--max-jobs=] [--max-time=] [--max-memory=]` | worker صف | DOC-061 |
| `tvn queue:listen` | worker با restart خودکار | DOC-061 |
| `tvn queue:restart` | سیگنال restart به workerها | DOC-061 |
| `tvn queue:retry {id\|--all}` | تلاش دوبارهٔ job مرده | DOC-061 |
| `tvn queue:failed` | فهرست jobهای مرده | DOC-061 |
| `tvn queue:flush` | پاک‌کردن jobهای مرده | DOC-061 |
| `tvn queue:prune-failed [--hours=]` | حذف jobهای مردهٔ قدیمی | DOC-061 |
| `tvn queue:monitor` | داشبورد صف | DOC-061 |
| `tvn outbox:relay [--once] [--batch=100]` | اجرای Outbox Relay | DOC-033 |
| `tvn schedule:run` | اجرای زمان‌بندی (در cron) | DOC-062 |
| `tvn schedule:list` | فهرست رویدادهای زمان‌بندی‌شده | DOC-062 |
| `tvn workflow:timers` | پردازش timerهای گردش‌کار | DOC-071 |

### ۲٫۱۰ امنیت و هویت (Security & Auth)
| دستور | توضیح | سند |
|---|---|---|
| `tvn key:generate` | تولید `APP_KEY` | DOC-054 |
| `tvn key:rotate` | چرخش کلید + بازرمزنگاری | DOC-054 |
| `tvn auth:logout-all` | خروج همهٔ نشست‌ها | DOC-056 |
| `tvn session:clear` | پاک‌کردن نشست‌ها | DOC-041 |
| `tvn security:scan` | اسکن آسیب‌پذیری (AST) | DOC-059 |
| `tvn security:sbom` | تولید SBOM | DOC-016 |

### ۲٫۱۱ نقش و دسترسی (RBAC & Audit)
| دستور | توضیح | سند |
|---|---|---|
| `tvn rbac:sync` | همگام‌سازی مجوزها از manifest | DOC-057 |
| `tvn rbac:check` | اعتبارسنجی مجوزها | DOC-101 |
| `tvn rbac:review --role=` | بازبینی دارندگان نقش | DOC-100 |
| `tvn rbac:orphans` | مجوزهای بدون نقش | DOC-100 |
| `tvn rbac:stale` | نقش‌های منقضی‌شده | DOC-100 |
| `tvn rbac:export [--format=csv]` | خروجی مجوزها | DOC-100 |
| `tvn audit:verify` | بررسی یکپارچگی زنجیرهٔ هش | DOC-058 |
| `tvn audit:export --from --to` | خروجی ممیزی | DOC-058 |
| `tvn audit:prune --older-than` | پاکسازی ممیزی قدیمی | DOC-058 |
| `tvn audit:show <id>` | نمایش یک رکورد ممیزی | DOC-058 |

### ۲٫۱۲ چنداجاره‌ای (Tenancy)
| دستور | توضیح | سند |
|---|---|---|
| `tvn tenant:create <id>` | ساخت tenant (DB+migrate+seed) | DOC-082E |
| `tvn tenant:migrate [--tenant=] [--strategy=]` | migrate روی tenantها | DOC-082E |
| `tvn tenant:delete <id>` | حذف tenant | DOC-082E |
| `tvn tenant:list` | فهرست tenantها | DOC-082E |
| `tvn tenant:stats` | مصرف منابع | DOC-082E |
| `tvn tenant:migrate-strategy` | تغییر استراتژی (A→B) | DOC-028 |

### ۲٫۱۳ گزارش و لاگ (Log)
| دستور | توضیح | سند |
|---|---|---|
| `tvn log:tail [channel] [-n 50] [-f]` | نمایش زندهٔ لاگ | DOC-036 |
| `tvn log:find <refCode>` | یافتن لاگ‌های یک refCode | DOC-036 |
| `tvn log:trace <traceId>` | بازسازی زنجیرهٔ درخواست | DOC-036 |
| `tvn log:clear --older-than=30d` | پاکسازی لاگ | DOC-036 |
| `tvn log:stats` | آمار لاگ | DOC-036 |
| `tvn log:rotate` | چرخش دستی | DOC-036 |

### ۲٫۱۴ کیفیت و تست (Quality & Test)
| دستور | توضیح | سند |
|---|---|---|
| `tvn test [--unit\|--group=\|--filter=\|--coverage\|--parallel=]` | اجرای تست‌ها | DOC-084E |
| `tvn bench:all [--fail-on-regression]` | بنچمارک‌ها | DOC-112 |
| `tvn arch:check` | بررسی قوانین معماری | DOC-021 |
| `tvn lint:php` | `php -l` روی همهٔ فایل‌ها | DOC-111 |
| `tvn style:check` / `style:fix` | سبک کد (CS-Fixer) | DOC-011 |
| `tvn lang:check` | کلیدهای ترجمهٔ گمشده/اضافی | DOC-069 |
| `tvn profile <url>` | پروفایل یک درخواست | DOC-067 |

### ۲٫۱۵ ابزار توسعه (Dev Tools)
| دستور | توضیح | سند |
|---|---|---|
| `tvn tinker` | REPL تعاملی | DOC-076 |
| `tvn container:graph` | گراف DOT وابستگی‌ها | DOC-026 |
| `tvn event:list` | فهرست رویدادها و Listenerها | DOC-033 |
| `tvn middleware:list` | فهرست middlewareها | DOC-076 |
| `tvn view:directives` | فهرست directiveهای ویو | DOC-043 |
| `tvn ide:generate [--format=phpstorm\|intelephense\|stubs]` | Stubهای IDE | DOC-082 |
| `tvn error:simulate <code>` | تولید صفحهٔ خطا (برای QA) | DOC-035 |
| `tvn api:docs [--version=] [--format=]` | تولید OpenAPI | DOC-044 |

### ۲٫۱۶ ارتقا و پشتیبان (Upgrade & Backup)
| دستور | توضیح | سند |
|---|---|---|
| `tvn upgrade --to=X.Y.Z` | ارتقای نسخه | DOC-081E |
| `tvn upgrade:check` | بررسی سازگاری | DOC-081E |
| `tvn upgrade:rollback` | بازگشت | DOC-081E |
| `tvn upgrade:status` | نسخهٔ فعلی و مسیر | DOC-081E |
| `tvn upgrade:patches` | فهرست patchها | DOC-081E |
| `tvn upgrade:layered` | مهاجرت معماری (mvc→hexagonal) | DOC-021 |
| `tvn backup:run` | پشتیبان‌گیری کامل | DOC-068 |
| `tvn backup:restore <id>` | بازیابی | DOC-068 |
| `tvn backup:prune [--keep-days=]` | پاکسازی backup قدیمی | DOC-068 |

---

## ۳. میان‌برها (Aliases) 🔒

| Alias | معادل |
|---|---|
| `tavaan` | `tvn` |
| `tvn s` | `tvn serve` |
| `tvn m` | `tvn migrate` |
| `tvn t` | `tvn test` |
| `tvn mk` | `tvn make` |
| `tvn c:clear` | `tvn optimize:clear` |

---

## ۴. cron و systemd (استقرار)

### ۴٫۱ cron (زمان‌بندی)
```cron
# هر دقیقه — تنها entry لازم برای scheduler
* * * * * cd /var/www/tavaan && php bin/tvn schedule:run >> /dev/null 2>&1
```

### ۴٫۲ systemd (worker صف)
```ini
# /etc/systemd/system/tavaan-worker.service
[Unit]
Description=Tavaan Queue Worker
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/var/www/tavaan
ExecStart=/usr/bin/php bin/tvn queue:work --sleep=3 --max-time=3600 --max-memory=128
Restart=always
RestartSec=5
# 🔒 graceful shutdown با SIGTERM
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

---

## ۵. الگوی پیاده‌سازی یک دستور

```php
<?php
declare(strict_types=1);

namespace Tavaan\Engine\Cache\Console;

use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Engine\Console\AsCommand;
use Tavaan\Engine\Console\Command;

#[AsCommand(name: 'cache:clear', description: 'پاک‌کردن حافظهٔ نهان')]
final class CacheClearCommand extends Command
{
    protected string $signature = 'cache:clear
        {store? : نام store (پیش‌فرض: default)}
        {--tags= : فقط تگ‌های مشخص (جدا‌شده با کاما)}
        {--force : بدون تأیید}';

    public function handle(): int
    {
        $store = $this->argument('store') ?? Cache::getDefaultDriver();

        if (!$this->option('force') && !$this->confirm("پاک‌کردن کش store={$store}؟")) {
            $this->warn('لغو شد.');
            return self::SUCCESS;
        }

        $tags = $this->option('tags');
        $tags
            ? Cache::store($store)->tags(\explode(',', $tags))->flush()
            : Cache::store($store)->flush();

        $this->info("✓ کش '{$store}' پاک شد.");
        return self::SUCCESS;
    }
}
```

---

## ۶. کشف خودکار دستورات

همهٔ کلاس‌های `*Command` در این مسیرها خودکار ثبت می‌شوند (با کش):
```
engines/*/src/**/Console/*Command.php
modules/*/src/Console/Commands/*Command.php
app/src/Console/Commands/*Command.php
```
→ `tvn` بدون آرگومان، همه را فهرست می‌کند (دسته‌بندی‌شده).

---

## ۷. معیار پذیرش 🧪
- AC-CLI-REF-01: همهٔ ۱۰۵ دستور ثبت‌شده و با `--help` مستند هستند.
- AC-CLI-REF-02: `tvn` بدون آرگومان → فهرست دسته‌بندی‌شدهٔ همهٔ دستورات.
- AC-CLI-REF-03: هر دستور کد خروج استاندارد (§1.1) برمی‌گرداند.
- AC-CLI-REF-04: گزینهٔ `--json` خروجی ماشین‌خوان می‌دهد.
- AC-CLI-REF-05: Aliasها (§3) کار می‌کنند.
- AC-CLI-REF-06: عملیات مخرب (`migrate:fresh`, `db:wipe`, `backup:restore`) در production نیازمند `--force` + تأیید هستند.
