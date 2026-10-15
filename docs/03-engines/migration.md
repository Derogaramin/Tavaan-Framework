# DOC-049 — موتور Migration | مهاجرت دیتابیس

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-049 |
| Engine ID | `tavaan.engine.migration` |
| Namespace | `Tavaan\Engine\Database\Migration` |
| اولویت | **P0** — فاز M3 |
| وابستگی | `schema`, `database` |
| Facade | ندارد (CLI) |
| LOC | ۸۰۰ |

## ۱. هدف
مدیریت نسخه‌دار تغییرات Schema با Lock برای اجرای همزمان، Rollback، Batch، Dry-Run
و مهاجرت داده‌ای.

## ۲. جدول migrationها
```sql
CREATE TABLE tvn_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(190) NOT NULL,     -- '2026_01_12_093000_create_orders_table'
  batch INT NOT NULL,
  module VARCHAR(64) NULL,             -- ماژول مالک
  ran_at DATETIME(3) NOT NULL,
  UNIQUE KEY uk_migration (migration)
) ENGINE=InnoDB;
```

## ۳. ساختار Migration
```php
// modules/shop/migrations/2026_01_12_093000_create_orders_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', fn (Blueprint $t) => /* … */);
    }
    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
```

## ۴. Migrator — الگوریتم
```php
final class Migrator
{
    public function run(array $paths, array $options = []): MigrateResult
    {
        $this->acquireLock();                       // 🔒 جلوگیری از اجرای همزمان
        try {
            $ran = $this->repository->getRan();      // migrationهای اجراشده
            $files = $this->getMigrationFiles($paths);
            $pending = \array_diff(\array_keys($files), $ran);
            \sort($pending);                         // 🔒 ترتیب زمانی

            if ($options['dry_run'] ?? false) {
                return MigrateResult::dryRun($pending);
            }

            $batch = $this->repository->getNextBatch();
            foreach ($pending as $name) {
                $migration = $this->resolve($files[$name]);
                $this->runMethod($migration, 'up', $name);
                $this->repository->log($name, $batch, $this->moduleOf($files[$name]));
                $this->output->writeln("<info>✓</info> {$name}");
            }
            return MigrateResult::success($pending);
        } finally {
            $this->releaseLock();
        }
    }

    public function rollback(int $steps = 1): void
    {
        $this->acquireLock();
        try {
            $lastBatch = $this->repository->getLastBatch($steps);
            foreach (\array_reverse($lastBatch) as $name) {     // 🔒 ترتیب معکوس
                $migration = $this->resolve($this->findFile($name));
                $this->runMethod($migration, 'down', $name);
                $this->repository->delete($name);
            }
        } finally { $this->releaseLock(); }
    }

    private function acquireLock(): void
    {
        $lock = $this->cache->lock('migration:lock', seconds: 300);
        if (!$lock->acquire(blocking: false)) {
            throw new MigrationLockedException(messageId: 'migration.locked',
                hint: 'یک migration دیگر در حال اجراست. کمی صبر کنید.');
        }
        $this->lock = $lock;
    }
}
```

## ۵. نکات مهم
🔒 هر migration باید `down()` معکوس دقیق `up()` باشد (برای rollback).
🔒 مهاجرت داده‌ای (نه schema) باید **قابل تکرار** (idempotent) و chunked باشد.
🔒 برای جداول بزرگ، از الگوی **Expand-Contract** استفاده شود (Zero-Downtime):
```
1. Expand:  افزودن ستون جدید (nullable)
2. Migrate: پرکردن تدریجی (background job, chunked)
3. Contract: حذف ستون قدیمی (در release بعد)
```

## ۶. دستورات CLI
| دستور | توضیح |
|---|---|
| `tvn migrate` | اجرای pending |
| `tvn migrate --module=shop` | فقط یک ماژول |
| `tvn migrate --dry-run` | نمایش بدون اجرا |
| `tvn migrate:fresh` | drop همه + migrate (⚠️ خطرناک) |
| `tvn migrate:refresh` | rollback همه + migrate |
| `tvn migrate:rollback --step=1` | بازگشت |
| `tvn migrate:status` | وضعیت |
| `tvn migrate:install` | ساخت جدول migrations |

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| اجرای همزمان مخرب | Lock |
| `migrate:fresh` در PROD | تأیید + `--force` + رد در production |
| Data loss در rollback | هشدار + backup پیشنهادی |

## ۸. تست
| T-01 | migrate → up اجرا، در repository ثبت |
| T-02 | rollback → down، ترتیب معکوس |
| T-03 | Lock: دو migrator همزمان → یکی صبر/خطا |
| T-04 | dry-run بدون تغییر DB |
| T-05 | batch numbering |
| T-06 | migrate:fresh در prod بدون force → رد |
| T-07 | Expand-Contract روی داده |

## ۹. معیار پذیرش 🧪
- AC-MIG-01..07. AC-MIG-08: migration ماژول‌ها جدا اجرا می‌شود.

## ۱۰. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.migration.v1
engine:
  id: tavaan.engine.migration
  namespace: Tavaan\Engine\Database\Migration
  path: engines/migration
  priority: P0
  phase: M3
  depends_on: [schema, database]
  facade: null
  tables: [tvn_migrations]
  public_api:
    - class: Migrator
      methods: [run, rollback, refresh, fresh, status, install]
  loc_estimate: 800
```
