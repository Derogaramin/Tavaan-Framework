# DOC-058 — موتور Audit | ممیزی ضدتقلب

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-058 |
| Engine ID | `tavaan.engine.audit` |
| Namespace | `Tavaan\Engine\Audit` |
| اولویت | **P1** — فاز M4 |
| وابستگی | `event`, `database`, `log`, `crypt` |
| Facade | `Audit` → `audit` |
| LOC | ۷۰۰ |

## ۱. هدف
ثبت **غیرقابل‌انکار** و **ضدتقلب** همهٔ تغییرات حساس با زنجیرهٔ هش (Hash Chain)،
Diff فیلدها، ثبت کاربر/IP/زمان، و قابلیت بازبینی.

## ۲. جدول
```sql
CREATE TABLE tvn_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audit_id CHAR(26) NOT NULL UNIQUE,
  event VARCHAR(50) NOT NULL,           -- created|updated|deleted|login|permission_changed|export
  auditable_type VARCHAR(190) NOT NULL,
  auditable_id VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  tenant_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  changed_fields JSON NULL,             -- ['status','total']
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  trace_id CHAR(32) NULL,
  prev_hash CHAR(64) NOT NULL,          -- 🔗 زنجیره
  hash CHAR(64) NOT NULL,               -- hash(prev_hash + payload)
  created_at DATETIME(3) NOT NULL,
  KEY idx_auditable (auditable_type, auditable_id),
  KEY idx_user (user_id), KEY idx_event (event), KEY idx_created (created_at)
) ENGINE=InnoDB;
```

## ۳. زنجیرهٔ هش (Tamper-Evident)
```php
public function record(AuditEvent $event): void
{
    $prevHash = $this->lastHash();                    // hash آخرین رکورد
    $payload = \json_encode([
        'event' => $event->name,
        'type'  => $event->auditableType,
        'id'    => $event->auditableId,
        'user'  => $event->userId,
        'old'   => $event->oldValues,
        'new'   => $event->newValues,
        'ts'    => $event->createdAt->format('Y-m-d\TH:i:s.uP'),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $hash = \hash_hmac('sha256', $prevHash . '|' . $payload, $this->auditKey);

    $this->db->table('tvn_audit_logs')->insert([
        'audit_id' => Ulid::generate()->toString(),
        'event' => $event->name, 'auditable_type' => $event->auditableType,
        'auditable_id' => $event->auditableId, 'user_id' => $event->userId,
        'old_values' => \json_encode($event->oldValues), 'new_values' => \json_encode($event->newValues),
        'changed_fields' => \json_encode(\array_keys($event->newValues)),
        'ip' => $event->ip, 'trace_id' => $event->traceId,
        'prev_hash' => $prevHash, 'hash' => $hash, 'created_at' => Clock::utcNow(),
    ]);
}

/** بررسی یکپارچگی زنجیره (tvn audit:verify) */
public function verify(): VerifyResult
{
    $prev = self::GENESIS_HASH;
    foreach ($this->all() as $row) {
        $expected = \hash_hmac('sha256', $prev . '|' . $this->payloadOf($row), $this->auditKey);
        if (!\hash_equals($expected, $row['hash']) || $row['prev_hash'] !== $prev) {
            return VerifyResult::tampered(at: $row['audit_id']);
        }
        $prev = $row['hash'];
    }
    return VerifyResult::intact();
}
```
🔒 اگر یک رکورد تغییر/حذف شود، همهٔ hashهای بعدی بی‌اعتبار می‌شوند → قابل تشخیص.
🔒 `auditKey` جدا از APP_KEY (Rotation متفاوت).

## ۴. Observer خودکار (برای Modelها)
```php
#[Observed(event: 'updated', fields: ['status','total'])]
final class Order extends Model { /* … */ }

// AuditObserver به‌صورت خودکار:
final class AuditObserver implements ObserverInterface
{
    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $this->audit->record(AuditEvent::update(
            auditableType: $model::class, auditableId: $model->getKey(),
            oldValues: \array_intersect_key($model->getOriginal(), $changes),
            newValues: $changes, userId: Auth::id(), ip: request()->ip(),
        ));
    }
}
```

## ۵. رویدادهای ممیزی‌شونده (اجباری)
| رویداد | چرا |
|---|---|
| ورود موفق/ناموفق | ردیابی دسترسی |
| تغییر نقش/مجوز | Privilege change |
| ایجاد/ویرایش/حذف دادهٔ حساس | انکارناپذیری |
| Export داده | نشت داده |
| تغییر تنظیمات | پیکربندی |
| تلاش CSRF/RateLimit | حمله |
| حذف انبوه | عملیات خطرناک |

## ۶. پیکربندی
```php
// config/audit.php
return [
    'enabled' => env_bool('TVN_AUDIT_ENABLED', true),
    'table' => 'tvn_audit_logs',
    'key' => env_string('TVN_AUDIT_KEY'),        // 🔒 جدا از APP_KEY
    'queue' => env_bool('TVN_AUDIT_QUEUE', false),  // async برای کارایی
    'events' => ['created','updated','deleted','login','logout','permission_changed','export','config_changed'],
    'exclude_fields' => ['password','remember_token','secret','token'],
    'retention_days' => 1825,                     // ۵ سال
    'immutable_storage' => false,                 // WORM
];
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| دستکاری لاگ | زنجیرهٔ هش + `audit:verify` |
| نشت PII در diff | `exclude_fields` + Mask |
| حجم زیاد | Queue + Retention + Partition |
| حذف توسط admin | DB user بدون DELETE + WORM storage |

## ۸. دستورات CLI
`audit:verify`, `audit:export --from --to`, `audit:prune --older-than`, `audit:show <id>`.

## ۹. تست
| T-01 | record با hash chain درست |
| T-02 | دستکاری یک رکورد → `verify` fail |
| T-03 | حذف یک رکورد → `verify` fail |
| T-04 | Observer خودکار روی Model |
| T-05 | `exclude_fields` mask می‌شود |
| T-06 | Queue mode async |

## ۱۰. معیار پذیرش 🧪
- AC-AUD-01..06. AC-AUD-07: `audit:verify` روی ۱۰۰۰۰ رکورد ≤ ۲s.

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.audit.v1
engine:
  id: tavaan.engine.audit
  namespace: Tavaan\Engine\Audit
  path: engines/audit
  priority: P1
  phase: M4
  depends_on: [event, database, log, crypt]
  facade: {name: Audit, accessor: audit}
  tables: [tvn_audit_logs]
  events_consumed: [model.created, model.updated, model.deleted, auth.login.succeeded, rbac.role.assigned]
  config_file: config/audit.php
  public_api:
    - class: Auditor
      methods: [record, verify, export, prune, lastHash]
  loc_estimate: 700
```
