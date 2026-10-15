# DOC-081E — موتور Upgrade | ارتقای نسخه

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-081E |
| Engine ID | `tavaan.engine.upgrade` |
| Namespace | `Tavaan\Engine\Upgrade` |
| اولویت | **P2** — فاز M8 |
| وابستگی | `console`, `database`, `filesystem` |
| LOC | ۶۰۰ |

## ۱. هدف
ارتقای امن نسخهٔ فریمورک و ماژول‌ها با Data Patch، Schema Migration، بررسی سازگاری
و Rollback — مشابه `migrate` اما برای ارتقای کل سیستم.

## ۲. جریان ارتقا
```
tvn upgrade --to=1.2.0
  │
  ├─ 1. Pre-check: سازگاری PHP، extensionها، فضا
  ├─ 2. Backup: DB + files (اجباری) 🔒
  ├─ 3. Maintenance mode: tvn down
  ├─ 4. تشخیص نسخهٔ فعلی → مسیر ارتقا (1.0 → 1.1 → 1.2)
  ├─ 5. برای هر گام:
  │      ├─ Schema migrations
  │      ├─ Data patches
  │      ├─ Config migrations (کلیدهای جدید/حذف)
  │      └─ Cache clear
  ├─ 6. Verify (health check)
  ├─ 7. tvn up
  └─ در صورت شکست: rollback از backup
```

## ۳. Data Patch
```php
// upgrades/1.1.0/patch_001_backfill_order_status.php
return new class extends DataPatch
{
    public string $version = '1.1.0';
    public string $description = 'Backfill order status';

    public function up(): void
    {
        DB::table('shop_orders')->whereNull('status')->chunkById(1000, function ($orders): void {
            foreach ($orders as $order) {
                DB::table('shop_orders')->where('id', $order->id)
                    ->update(['status' => $order->paid_at ? 'paid' : 'pending']);
            }
        });
    }

    public function down(): void
    {
        DB::table('shop_orders')->where('status', 'pending')->update(['status' => null]);
    }
};
```
🔒 Patchها باید **idempotent** و **chunked** باشند (برای جداول بزرگ).

## ۴. Config Migration
```php
// وقتی کلید config تغییر نام/حذف می‌شود
final class ConfigMigrator
{
    public function migrate(array $changes): void
    {
        foreach ($changes as $change) {
            match ($change['type']) {
                'rename' => $this->rename($change['from'], $change['to']),
                'remove' => $this->remove($change['key']),
                'default' => $this->setDefault($change['key'], $change['value']),
                'transform' => $this->transform($change['key'], $change['callback']),
            };
        }
    }
}
```

## ۵. سازگاری نسخه (SemVer)
```php
final class VersionResolver
{
    /** @return list<string> مسیر ارتقا */
    public function upgradePath(string $from, string $to): array
    {
        $versions = $this->availableVersions();
        $path = [];
        $current = $from;
        while ($current !== $to) {
            $next = $this->nextVersion($current, $versions);
            if ($next === null) { throw new UpgradeException(messageId: 'upgrade.no_path'); }
            $path[] = $next;
            $current = $next;
        }
        return $path;      // ['1.0.1','1.1.0','1.2.0']
    }
}
```
🔒 ارتقا فقط **گام‌به‌گام** (نه پرش از 1.0 به 2.0 بدون میانی).
🔒 Major version (2.0) ممکن است Breaking باشد → نیازمند ADR + راهنمای مهاجرت.

## ۶. Rollback
```php
public function rollback(): void
{
    $backup = $this->lastBackup();
    if ($backup === null) { throw new NoBackupException(); }

    $this->restoreDatabase($backup);
    $this->restoreFiles($backup);
    $this->cache->clear();
    $this->output->warn('بازگشت به نسخهٔ ' . $backup->version);
}
```

## ۷. دستورات CLI
| دستور | توضیح |
|---|---|
| `tvn upgrade --to=X.Y.Z` | ارتقا |
| `tvn upgrade:check` | بررسی سازگاری بدون اجرا |
| `tvn upgrade:rollback` | بازگشت |
| `tvn upgrade:status` | نسخهٔ فعلی و مسیر |
| `tvn upgrade:patches` | فهرست patchها |

## ۸. امنیت
| تهدید | کنترل |
|---|---|
| Data loss | Backup اجباری قبل از ارتقا |
| ارتقای مخرب | بررسی امضای بستهٔ ارتقا |
| شکست میانی | Transaction + rollback |
| downtime | Maintenance mode + health check |

## ۹. تست
| T-01 | upgrade path (1.0→1.2) |
| T-02 | data patch idempotent |
| T-03 | config migration |
| T-04 | rollback از backup |
| T-05 | pre-check شکست (PHP ناسازگار) |
| T-06 | maintenance mode |

## ۱۰. معیار پذیرش 🧪
- AC-UPG-01..06. AC-UPG-07: ارتقا بدون downtime برای patch version (با strategy).

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.upgrade.v1
engine:
  id: tavaan.engine.upgrade
  namespace: Tavaan\Engine\Upgrade
  path: engines/upgrade
  priority: P2
  phase: M8
  depends_on: [console, database, filesystem]
  facade: null
  public_api:
    - class: Upgrader
      methods: [upgrade, check, rollback, status, patches]
  loc_estimate: 600
```
