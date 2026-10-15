# DOC-086 — کاتالوگ طرح پایگاه داده (Database Schema Catalog) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-086 |
| وضعیت | Approved v1.0 |
| نقش | دیکشنری دادهٔ یکپارچه — همهٔ جداول هسته (`tvn_*`) که در مستندات پراکنده‌اند |
| مرتبط | DOC-047 (database)، DOC-049 (migration)، DOC-012 (نام‌گذاری DB) |

---

## ۱. هدف

جداول هستهٔ فریمورک در ~۱۵ سند موتور پراکنده‌اند. این سند **دیکشنری دادهٔ یگانه** است:
هر جدول هسته با مالک (موتور)، ستون‌ها، ایندکس‌ها و ارجاع به سند جزئیات.

> 🔒 جداول **ماژول‌های دامنه‌ای** (shop_*, blog_*, erp_* …) در بلوپرینت هر کاربرد مستند شده‌اند
> (DOC-091..098) و در migration خودِ ماژول ساخته می‌شوند — نه در هسته.

---

## ۲. قواعد عمومی همهٔ جداول (DOC-012 §5)

| قاعده | جزئیات |
|---|---|
| کلید اصلی | `id` — یا `BIGINT UNSIGNED AUTO_INCREMENT` یا `CHAR(26)` (ULID) |
| زمان | `created_at DATETIME(3) NOT NULL` (UTC)، `updated_at DATETIME(3) NULL` |
| نرم‌حذف | `deleted_at DATETIME(3) NULL` (در صورت نیاز) |
| ممیزی | `created_by`, `updated_by` (BIGINT NULL) برای جداول حساس |
| چنداجاره‌ای | `tenant_id BIGINT UNSIGNED NULL` وقتی tenancy فعال است |
| Engine/Charset | `InnoDB` + `utf8mb4` + `utf8mb4_0900_ai_ci` |
| پیشوند هسته | `tvn_` (جداول فریمورک) |
| پیشوند ماژول | `<module>_` (مثل `shop_`) |

---

## ۳. فهرست جداول هسته به تفکیک موتور

### ۳٫۱ سیستم / اجرا
| جدول | موتور | سند | توضیح |
|---|---|---|---|
| `tvn_migrations` | migration | DOC-049 | وضعیت migrationها |
| `tvn_tenants` | tenancy | DOC-028 | ثبت tenantها |
| `cache` | cache | DOC-060 | درایور database کش |
| `cache_locks` | cache | DOC-060 | قفل‌های اتمیک |
| `sessions` | session | DOC-041 | درایور database نشست |
| `logs` | log | DOC-036 | درایور database لاگ (اختیاری) |

### ۳٫۲ رویداد و صف
| جدول | موتور | سند | توضیح |
|---|---|---|---|
| `tvn_outbox` | event | DOC-033 | Transactional Outbox |
| `tvn_inbox` | event | DOC-033 | Idempotency مصرف‌کننده |
| `tvn_events` | event (sourcing) | DOC-023 | Event Store (اختیاری) |
| `tvn_snapshots` | event (sourcing) | DOC-023 | Snapshotها (اختیاری) |
| `tvn_jobs` | queue | DOC-061 | صف jobها |
| `tvn_failed_jobs` | queue | DOC-061 | jobهای مرده |
| `tvn_job_batches` | queue | DOC-061 | دسته‌های job |
| `tvn_sagas` | workflow | DOC-024 | وضعیت Sagaها |

### ۳٫۳ هویت و دسترسی
| جدول | موتور | سند | توضیح |
|---|---|---|---|
| `users` | core-user (ماژول) | DOC-097 | کاربران (مالک: ماژول core-user) |
| `password_resets` | auth | DOC-056 | توکن بازیابی رمز |
| `personal_access_tokens` | auth | DOC-056 | توکن‌های API (opaque) |
| `user_devices` | auth | DOC-056 | نشست‌های فعال/دستگاه‌ها |
| `two_factor_secrets` | auth | DOC-056 | راز 2FA/TOTP |
| `tvn_permissions` | rbac | DOC-057 | کاتالوگ مجوزها |
| `tvn_roles` | rbac | DOC-057 | نقش‌ها (با سلسله‌مراتب) |
| `tvn_role_user` | rbac | DOC-057 | تخصیص نقش به کاربر |
| `tvn_permission_role` | rbac | DOC-057 | تخصیص مجوز به نقش |
| `tvn_delegations` | rbac | DOC-057 | تفویض اختیار موقت |
| `tvn_duties` | duties | DOC-102 | تعریف وظایف |
| `tvn_task_assignments` | duties | DOC-102 | تخصیص وظیفه |
| `tvn_audit_logs` | audit | DOC-058 | ممیزی با زنجیرهٔ هش |

### ۳٫۴ گردش‌کار (BPMS)
| جدول | موتور | سند | توضیح |
|---|---|---|---|
| `tvn_workflows` | workflow | DOC-071 | تعریف فرایندها |
| `tvn_process_instances` | workflow | DOC-071 | نمونه‌های فرایند |
| `tvn_process_tasks` | workflow | DOC-071 | کارهای انسانی |
| `tvn_process_history` | workflow | DOC-071 | تاریخچهٔ گذارها |

### ۳٫۵ رسانه، آگاه‌سازی، پرداخت
| جدول | موتور | سند | توضیح |
|---|---|---|---|
| `tvn_media` | media | DOC-074 | کتابخانهٔ رسانه |
| `tvn_notifications` | notification | DOC-066 | آگاه‌سازی درون‌برنامه |
| `tvn_payment_transactions` | payment | DOC-073 | تراکنش‌های پرداخت |

---

## ۴. تعریف DDL جداول بحرانی هسته

> جداولی که چند موتور به آن‌ها وابسته‌اند یا رفتار ویژه دارند. بقیه در سند موتور خود تعریف شده‌اند.

### ۴٫۱ `tvn_outbox` (DOC-023 §6.3)
```sql
CREATE TABLE tvn_outbox (
  id              CHAR(26)      NOT NULL PRIMARY KEY,
  aggregate_type  VARCHAR(100)  NOT NULL,
  aggregate_id    VARCHAR(64)   NOT NULL,
  event_name      VARCHAR(150)  NOT NULL,
  event_version   SMALLINT      NOT NULL DEFAULT 1,
  payload         JSON          NOT NULL,
  headers         JSON          NULL,
  trace_id        CHAR(32)      NULL,
  causation_id    CHAR(26)      NULL,
  correlation_id  CHAR(26)      NULL,
  sequence        BIGINT        NOT NULL,
  idempotency_key CHAR(64)      NOT NULL,
  status          VARCHAR(16)   NOT NULL DEFAULT 'new',   -- new|sending|sent|failed|dead
  attempts        TINYINT       NOT NULL DEFAULT 0,
  available_at    DATETIME(3)   NOT NULL,
  created_at      DATETIME(3)   NOT NULL,
  sent_at         DATETIME(3)   NULL,
  last_error      TEXT          NULL,
  UNIQUE KEY uk_outbox_idem (idempotency_key),
  KEY idx_outbox_pending (status, available_at),
  KEY idx_outbox_agg (aggregate_type, aggregate_id, sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### ۴٫۲ `tvn_jobs` (DOC-061 §5)
```sql
CREATE TABLE tvn_jobs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue         VARCHAR(64)     NOT NULL,
  payload       LONGTEXT        NOT NULL,
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reserved_at   INT UNSIGNED    NULL,
  available_at  INT UNSIGNED    NOT NULL,
  created_at    INT UNSIGNED    NOT NULL,
  KEY idx_queue_avail (queue, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
🔒 `SELECT … FOR UPDATE SKIP LOCKED` در pop برای workerهای موازی (نیازمند MySQL 8+/PG 9.5+).

### ۴٫۳ `tvn_audit_logs` (DOC-058 §2)
```sql
CREATE TABLE tvn_audit_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  audit_id       CHAR(26)      NOT NULL UNIQUE,
  event          VARCHAR(50)   NOT NULL,
  auditable_type VARCHAR(190)  NOT NULL,
  auditable_id   VARCHAR(64)   NOT NULL,
  user_id        BIGINT UNSIGNED NULL,
  tenant_id      BIGINT UNSIGNED NULL,
  old_values     JSON          NULL,
  new_values     JSON          NULL,
  changed_fields JSON          NULL,
  ip             VARCHAR(45)   NULL,
  user_agent     VARCHAR(255)  NULL,
  trace_id       CHAR(32)      NULL,
  prev_hash      CHAR(64)      NOT NULL,     -- 🔗 زنجیرهٔ هش
  hash           CHAR(64)      NOT NULL,
  created_at     DATETIME(3)   NOT NULL,
  KEY idx_auditable (auditable_type, auditable_id),
  KEY idx_user (user_id), KEY idx_event (event), KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
🔒 DB user در production نباید `DELETE` روی این جدول داشته باشد (انکارناپذیری).

### ۴٫۴ `cache` (DOC-060)
```sql
CREATE TABLE cache (
  `key`       VARCHAR(255) NOT NULL PRIMARY KEY,
  value       LONGTEXT     NOT NULL,
  expiration  INT UNSIGNED NOT NULL,          -- unix timestamp؛ 0 = بدون انقضا
  KEY idx_expiration (expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cache_locks (
  `key`      VARCHAR(255) NOT NULL PRIMARY KEY,
  owner      VARCHAR(255) NOT NULL,
  expiration INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### ۴٫۵ `sessions` (DOC-041)
```sql
CREATE TABLE sessions (
  id             VARCHAR(64)  NOT NULL PRIMARY KEY,
  user_id        BIGINT UNSIGNED NULL,
  ip_address     VARCHAR(45)  NULL,
  user_agent     TEXT         NULL,
  payload        LONGTEXT     NOT NULL,        -- 🔒 JSON (نه serialize)
  last_activity  INT UNSIGNED NOT NULL,
  KEY idx_last_activity (last_activity),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### ۴٫۶ RBAC (DOC-057 §2.1)
```sql
CREATE TABLE tvn_permissions (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module    VARCHAR(64)  NOT NULL,
  `key`     VARCHAR(190) NOT NULL UNIQUE,
  title_fa  VARCHAR(190) NULL,
  title_en  VARCHAR(190) NULL,
  group_id  VARCHAR(64)  NULL,
  created_at DATETIME(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tvn_roles (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`     VARCHAR(64)  NOT NULL UNIQUE,
  name      VARCHAR(190) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  is_system TINYINT(1)   NOT NULL DEFAULT 0,
  scope     VARCHAR(32)  NOT NULL DEFAULT 'global',
  created_at DATETIME(3) NOT NULL,
  CONSTRAINT fk_role_parent FOREIGN KEY (parent_id) REFERENCES tvn_roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tvn_role_user (
  role_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  tenant_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME(3) NULL,
  granted_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (role_id, user_id, tenant_id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tvn_permission_role (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ۵. گراف وابستگی جداول هسته (FK)

```
tvn_roles ──┐ (parent_id → خود)
            │
tvn_permissions ──< tvn_permission_role >── tvn_roles
                                              │
users ────────────< tvn_role_user >───────────┘
   │
   ├──────────< tvn_audit_logs (user_id)
   ├──────────< tvn_task_assignments (assignee_id)
   └──────────< tvn_delegations (from/to_user_id)

tvn_outbox / tvn_inbox / tvn_jobs     → بدون FK (standalone, کلید منطقی)
tvn_process_instances → tvn_workflows (definition_id)
tvn_process_tasks     → tvn_process_instances (instance_id)
tvn_media             → polymorphic (mediable_type/id) — بدون FK
tvn_notifications     → polymorphic (notifiable_type/id) — بدون FK
```

🔒 **قاعده:** جداول بین‌ماژولی FK ندارند (DOC-022 §6). جداول هسته فقط به `users`
و خودشان FK دارند. جداول polymorphic هرگز FK ندارند ( integrity در Application).

---

## ۶. توالی ساخت (Migration Order)

```
۱. users (ماژول core-user)          ← پایهٔ همه
۲. tvn_tenants                       ← اگر tenancy فعال
۳. tvn_permissions, tvn_roles        ← RBAC
۴. tvn_role_user, tvn_permission_role
۵. sessions, cache, cache_locks      ← وب
۶. tvn_outbox, tvn_inbox             ← event
۷. tvn_jobs, tvn_failed_jobs, tvn_job_batches  ← queue
۸. tvn_audit_logs                    ← audit
۹. tvn_migrations                    ← (اولین، توسط migrate:install)
۱۰. بقیه بر اساس ماژول فعال
```
🔒 `tvn_migrations` اولین جدول است (توسط `tvn migrate:install`).

---

## ۷. ابزار

| دستور | کاربرد |
|---|---|
| `tvn migrate` | ساخت همهٔ جداول هسته (از `database/migrations/`) |
| `tvn db:show` | فهرست جداول + حجم + تعداد ردیف |
| `tvn schema:diff` | مقایسهٔ schema فعلی با migrationها |
| `tvn db:tenant-guard` | بررسی کوئری‌های بدون tenant scope |

---

## ۸. معیار پذیرش 🧪
- AC-DB-CAT-01: `tvn migrate` همهٔ جداول هستهٔ §3 را به ترتیب §6 می‌سازد.
- AC-DB-CAT-02: هر جدول هسته یک مالک (موتور) یکتا دارد.
- AC-DB-CAT-03: جداول بین‌ماژولی FK ندارند (بررسی خودکار).
- AC-DB-CAT-04: `tvn_audit_logs` در production بدون مجوز DELETE است.
- AC-DB-CAT-05: همهٔ جداول `utf8mb4` + `InnoDB` هستند.

---

## ۹. بلوک ماشین‌خوان 🧠

```yaml
# machine-readable: tavaan.database.catalog.v1
core_tables:
  system: [tvn_migrations, tvn_tenants, cache, cache_locks, sessions, logs]
  event_queue: [tvn_outbox, tvn_inbox, tvn_events, tvn_snapshots, tvn_jobs,
                tvn_failed_jobs, tvn_job_batches, tvn_sagas]
  identity_access: [users, password_resets, personal_access_tokens, user_devices,
                    two_factor_secrets, tvn_permissions, tvn_roles, tvn_role_user,
                    tvn_permission_role, tvn_delegations, tvn_duties,
                    tvn_task_assignments, tvn_audit_logs]
  workflow: [tvn_workflows, tvn_process_instances, tvn_process_tasks, tvn_process_history]
  content: [tvn_media, tvn_notifications, tvn_payment_transactions]
conventions:
  engine: InnoDB
  charset: utf8mb4
  collation: utf8mb4_0900_ai_ci
  core_prefix: "tvn_"
  module_prefix: "<module>_"
  pk: [bigint_unsigned_autoincrement, char_26_ulid]
  timestamps: {created_at: "DATETIME(3) NOT NULL", updated_at: "DATETIME(3) NULL"}
no_cross_module_fk: true
polymorphic_no_fk: true
```
