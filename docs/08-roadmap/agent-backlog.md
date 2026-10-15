# DOC-121 — بک‌لاگ ایجنت (Agent Backlog) 🧠

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-121 |
| نقش | تیکت‌های آمادهٔ اجرا برای ایجنت‌های هوش مصنوعی |
| قالب | machine-readable برای پیمایش خودکار |
| مرتبط | DOC-120 (فازها)، DOC-030 (رجیستری موتورها) |

---

## ۱. ساختار یک تیکت

```yaml
ticket:
  id: TVN-XXXX
  title: <عنوان کوتاه>
  phase: M1
  engine: <engine-id>
  type: engine|module|feature|fix|docs|test
  priority: P0|P1|P2
  depends_on: [TVN-YYYY]        # تیکت‌های پیش‌نیاز
  blocked_by: []
  estimate_loc: <عدد>
  docs: [DOC-XXX]               # اسناد مرجع
  acceptance:                   # معیار پذیرش (قابل تست)
    - <AC-1>
    - <AC-2>
  files_to_create: []
  files_to_modify: []
  agent_hints: <راهنمایی برای ایجنت>
  status: todo|in_progress|review|done
```

---

## ۲. تیکت‌های فاز M1 (هسته)

```yaml
tickets:
  - id: TVN-0001
    title: "Autoloader خودنوشته با ClassMap"
    phase: M1
    engine: autoloader (core)
    type: engine
    priority: P0
    depends_on: []
    docs: [DOC-027, DOC-082]
    estimate_loc: 400
    acceptance:
      - "پروژه بدون vendor/ بالا می‌آید"
      - "PSR-4 + wildcard (engines/*/src) کار می‌کند"
      - "Path traversal → AutoloadException"
      - "ClassMap cache ≥ ۷۰٪ سریع‌تر"
    files_to_create:
      - core/Autoloading/Autoloader.php
      - core/Autoloading/AutoloaderRegistry.php
      - core/Autoloading/ClassMapGenerator.php
      - core/bootstrap.php
    agent_hints: "DOC-027 §2 را دقیق پیاده کن. locked_prefixes برای امنیت. sortPrefixesByLength."

  - id: TVN-0002
    title: "Container با Auto-Wiring"
    phase: M1
    engine: tavaan.engine.container
    type: engine
    priority: P0
    depends_on: [TVN-0001]
    docs: [DOC-031, DOC-026]
    estimate_loc: 1200
    acceptance: [AC-CON-01..06]
    files_to_create: [engines/container/**]
    agent_hints: "ReflectionCache حیاتی است. Union/DNF resolve با قطعیت. CycleDetector با مسیر کامل."

  - id: TVN-0003
    title: "Config Repository + ENV Loader"
    phase: M1
    engine: tavaan.engine.config
    type: engine
    priority: P0
    depends_on: [TVN-0002]
    docs: [DOC-032]
    estimate_loc: 600
    acceptance: [AC-CFG-01..06]
    agent_hints: "بدون putenv! Dot-path get/set. Merge: list جایگزین، map ادغام. Schema validation."

  - id: TVN-0004
    title: "Event Dispatcher + Outbox"
    phase: M1
    engine: tavaan.engine.event
    type: engine
    priority: P0
    depends_on: [TVN-0002, TVN-0003]
    docs: [DOC-033, DOC-023]
    estimate_loc: 900
    acceptance: [AC-EVT-01..13]
    agent_hints: "Wildcard Trie. Priority قطعی. Outbox در تراکنش + Relay با SKIP LOCKED."

  - id: TVN-0005
    title: "Pipeline"
    phase: M1
    engine: tavaan.engine.pipeline
    type: engine
    priority: P0
    depends_on: [TVN-0002]
    docs: [DOC-034]
    estimate_loc: 250
    acceptance: [AC-PIP-01..04]

  - id: TVN-0006
    title: "Error/Exception Handler + LogMasker"
    phase: M1
    engine: tavaan.engine.error
    type: engine
    priority: P0
    depends_on: [TVN-0007]
    docs: [DOC-035, DOC-013]
    estimate_loc: 800
    acceptance: [AC-ERR-01..11]
    agent_hints: "LogMasker با pattern. RefCode. Deprecated→failure در CI. Renderers (Json/Html/Cli/Debug)."

  - id: TVN-0007
    title: "Log Engine (چندکاناله + Mask)"
    phase: M1
    engine: tavaan.engine.log
    type: engine
    priority: P0
    depends_on: [TVN-0003]
    docs: [DOC-036]
    estimate_loc: 900
    acceptance: [AC-LOG-01..05]

  - id: TVN-0008
    title: "Compat/Polyfill Engine"
    phase: M1
    engine: tavaan.engine.compat
    type: engine
    priority: P0
    depends_on: []
    docs: [DOC-083E]
    estimate_loc: 400
    acceptance: [AC-CMP-01..05]
    agent_hints: "Decimal بدون bcmath. ZipWriterPure. MagicBytes. StreamHttpTransport. Utf8."

  - id: TVN-0009
    title: "Optimizer (cacheها)"
    phase: M1
    engine: tavaan.engine.optimizer
    type: engine
    priority: P1
    depends_on: [TVN-0003]
    docs: [DOC-037]
    estimate_loc: 800
    acceptance: [AC-OPT-01..03]

  - id: TVN-0010
    title: "Module Registry + Topological Sort"
    phase: M1
    engine: tavaan.engine.module
    type: engine
    priority: P0
    depends_on: [TVN-0002, TVN-0003]
    docs: [DOC-038, DOC-022]
    estimate_loc: 900
    acceptance: [AC-MOD-01..08]

  - id: TVN-0011
    title: "Console Engine (CLI framework)"
    phase: M1
    engine: tavaan.engine.console
    type: engine
    priority: P0
    depends_on: [TVN-0002, TVN-0004]
    docs: [DOC-076]
    estimate_loc: 1600
    acceptance: [AC-CLI-01..04]
    agent_hints: "bin/tvn entry. Signature parser. رنگی. Command discovery + cache."
```

---

## ۳. تیکت‌های M2 (وب) — خلاصه

```yaml
  - {id: TVN-0020, title: "HTTP Request/Response/Kernel", engine: http, phase: M2, depends_on: [TVN-0005,TVN-0006], docs: [DOC-039], loc: 2800}
  - {id: TVN-0021, title: "Router با Compiled Trie", engine: router, phase: M2, depends_on: [TVN-0020], docs: [DOC-040], loc: 1400}
  - {id: TVN-0022, title: "Session (۵ درایور)", engine: session, phase: M2, depends_on: [TVN-0020,TVN-0054], docs: [DOC-041], loc: 900}
  - {id: TVN-0023, title: "Cookie رمزنگاری‌شده", engine: cookie, phase: M2, depends_on: [TVN-0020,TVN-0054], docs: [DOC-042], loc: 400}
  - {id: TVN-0024, title: "View Engine (.tv.php)", engine: view, phase: M2, depends_on: [TVN-0004,TVN-0043], docs: [DOC-043], loc: 1800}
  - {id: TVN-0025, title: "i18n", engine: i18n, phase: M2, depends_on: [TVN-0003], docs: [DOC-069], loc: 900}
  - {id: TVN-0026, title: "Jalali Calendar", engine: jalali, phase: M2, depends_on: [TVN-0025], docs: [DOC-070], loc: 800}
  - {id: TVN-0027, title: "CORS", engine: cors, phase: M2, depends_on: [TVN-0020], docs: [DOC-046], loc: 350}
```

## ۴. تیکت‌های M3 (داده) — خلاصه
```yaml
  - {id: TVN-0030, title: "Database + QueryBuilder + Grammar", engine: database, phase: M3, depends_on: [TVN-0003,TVN-0004,TVN-0007], docs: [DOC-047], loc: 3200}
  - {id: TVN-0031, title: "Schema Builder + Blueprint", engine: schema, phase: M3, depends_on: [TVN-0030], docs: [DOC-048], loc: 1100}
  - {id: TVN-0032, title: "Migration", engine: migration, phase: M3, depends_on: [TVN-0031], docs: [DOC-049], loc: 800}
  - {id: TVN-0033, title: "ORM (ActiveRecord + Repository)", engine: orm, phase: M3, depends_on: [TVN-0030,TVN-0004], docs: [DOC-050], loc: 4000}
  - {id: TVN-0034, title: "Pagination", engine: pagination, phase: M3, depends_on: [TVN-0030], docs: [DOC-053], loc: 300}
  - {id: TVN-0035, title: "Validation (۸۵ قاعده)", engine: validation, phase: M3, depends_on: [TVN-0025,TVN-0030], docs: [DOC-051], loc: 2200}
```

## ۵. تیکت‌های M4 (امنیت) — خلاصه
```yaml
  - {id: TVN-0054, title: "Crypt (AES-GCM + HKDF)", engine: crypt, phase: M4, depends_on: [TVN-0003], docs: [DOC-054], loc: 600}
  - {id: TVN-0055, title: "Hash (Argon2id)", engine: hash, phase: M4, depends_on: [TVN-0003], docs: [DOC-055], loc: 350}
  - {id: TVN-0056, title: "Auth (Session/JWT/OTP/2FA)", engine: auth, phase: M4, depends_on: [TVN-0022,TVN-0055,TVN-0054,TVN-0004], docs: [DOC-056], loc: 2000}
  - {id: TVN-0057, title: "RBAC (Gate/Policy)", engine: rbac, phase: M4, depends_on: [TVN-0056,TVN-0030,TVN-0060], docs: [DOC-057], loc: 1600}
  - {id: TVN-0059, title: "Security (CSRF/RateLimit/SSRF/Sanitizer)", engine: security, phase: M4, depends_on: [TVN-0020,TVN-0054,TVN-0060], docs: [DOC-059], loc: 1500}
  - {id: TVN-0060, title: "Cache (۶ درایور)", engine: cache, phase: M4, depends_on: [TVN-0003,TVN-0004], docs: [DOC-060], loc: 1400}
  - {id: TVN-0058, title: "Audit (hash chain)", engine: audit, phase: M4, depends_on: [TVN-0004,TVN-0030,TVN-0007,TVN-0054], docs: [DOC-058], loc: 700}
```

## ۶. تیکت‌های M5-M9 — خلاصهٔ فشرده
```yaml
# M5
  - {id: TVN-0061, title: "Queue + Worker", engine: queue, phase: M5, docs: [DOC-061]}
  - {id: TVN-0062, title: "Schedule + Cron", engine: schedule, phase: M5, docs: [DOC-062]}
  - {id: TVN-0063, title: "Filesystem (local/s3/ftp)", engine: filesystem, phase: M5, docs: [DOC-063]}
  - {id: TVN-0074, title: "Media (image processing)", engine: media, phase: M5, docs: [DOC-074]}
  - {id: TVN-0065, title: "Mail (SMTP خودنوشته)", engine: mail, phase: M5, docs: [DOC-065]}
  - {id: TVN-0066, title: "Notification (چندکاناله)", engine: notification, phase: M5, docs: [DOC-066]}
  - {id: TVN-0064, title: "HttpClient + SSRF", engine: http-client, phase: M5, docs: [DOC-064]}
  - {id: TVN-0068, title: "Backup", engine: backup, phase: M5, docs: [DOC-068]}
# M6
  - {id: TVN-0044, title: "API (Resource/OpenAPI)", engine: api, phase: M6, docs: [DOC-044]}
  - {id: TVN-0067, title: "Observability (Metrics/Trace)", engine: observability, phase: M6, docs: [DOC-067]}
  - {id: TVN-0052, title: "Search (فارسی)", engine: search, phase: M6, docs: [DOC-052]}
  - {id: TVN-0082, title: "Tenancy", engine: tenancy, phase: M6, docs: [DOC-082E]}
  - {id: TVN-0075, title: "FeatureFlag", engine: feature-flag, phase: M6, docs: [DOC-075]}
  - {id: TVN-0045, title: "Realtime (SSE/WS)", engine: realtime, phase: M6, docs: [DOC-045]}
# M7
  - {id: TVN-0071, title: "Workflow (BPM)", engine: workflow, phase: M7, docs: [DOC-071]}
  - {id: TVN-0072, title: "Report (xlsx خودنوشته)", engine: report, phase: M7, docs: [DOC-072]}
  - {id: TVN-0073, title: "Payment (درگاه‌ها)", engine: payment, phase: M7, docs: [DOC-073]}
# M8
  - {id: TVN-0077, title: "Scaffold (make:*)", engine: scaffold, phase: M8, docs: [DOC-077]}
  - {id: TVN-0078, title: "Admin (CRUD generator)", engine: admin, phase: M8, docs: [DOC-078]}
  - {id: TVN-0079, title: "Plugin (sandbox)", engine: plugin, phase: M8, docs: [DOC-079]}
  - {id: TVN-0084, title: "Test Framework", engine: test, phase: M8, docs: [DOC-084E]}
  - {id: TVN-0081, title: "Upgrade", engine: upgrade, phase: M8, docs: [DOC-081E]}
# M9
  - {id: TVN-0091, title: "Kit: Blog", phase: M9, docs: [DOC-091]}
  - {id: TVN-0092, title: "Kit: News Portal", phase: M9, docs: [DOC-092]}
  - {id: TVN-0093, title: "Kit: Shop", phase: M9, docs: [DOC-093]}
  - {id: TVN-0094, title: "Kit: Forum", phase: M9, docs: [DOC-094]}
  - {id: TVN-0095, title: "Kit: LMS", phase: M9, docs: [DOC-095]}
  - {id: TVN-0096, title: "Kit: CRM", phase: M9, docs: [DOC-096]}
  - {id: TVN-0097, title: "Kit: ERP", phase: M9, docs: [DOC-097]}
  - {id: TVN-0098, title: "Kit: BPMS", phase: M9, docs: [DOC-098]}
```

---

## ۷. الگوریتم انتخاب تیکت برای ایجنت

```
function pickNextTicket(agent):
    ready = [t for t in tickets if t.status == 'todo'
             and all(dep.status == 'done' for dep in t.depends_on)]
    if ready is empty:
        return "همهٔ تیکت‌ها blocked یا done هستند"
    # اولویت: phase صعودی، سپس priority، سپس وابستگی کمتر
    return sorted(ready, key=(phase, priority, -len(depends_on)))[0]
```

---

## ۸. راهنمای اجرای ایجنت برای هر تیکت

```
1. docs تیکت را بخوان (DOC-XXX)
2. DOC-010 (قواعد PHP) و DOC-011 (سبک) را مرور کن
3. ساختار پوشه را از DOC-080 بررسی کن
4. Template موتور (DOC-150) را برای مستند استفاده کن
5. کد را بنویس (Contract-first)
6. تست‌ها را بر اساس acceptance criteria بنویس
7. بلوک machine-readable مستند را پر کن
8. DoD (DOC-113) را چک کن
9. tvn test + arch:check + security:scan
```

---

## ۹. وضعیت تیکت‌ها (Dashboard)

| فاز | کل | todo | in_progress | done |
|---|---|---|---|---|
| M1 | 11 | 11 | 0 | 0 |
| M2 | 8 | 8 | 0 | 0 |
| M3 | 6 | 6 | 0 | 0 |
| M4 | 7 | 7 | 0 | 0 |
| M5 | 9 | 9 | 0 | 0 |
| M6 | 6 | 6 | 0 | 0 |
| M7 | 3 | 3 | 0 | 0 |
| M8 | 5 | 5 | 0 | 0 |
| M9 | 8 | 8 | 0 | 0 |
| **جمع** | **63** | **63** | **0** | **0** |

> ⚠️ این فایل با پیشرفت پروژه به‌روز می‌شود. `status` هر تیکت را پس از اتمام تغییر دهید.
