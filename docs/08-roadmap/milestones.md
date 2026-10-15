# DOC-120 — نقشهٔ راه و فازها (Roadmap & Milestones)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-120 |
| مرتبط | DOC-030 (رجیستری موتورها)، DOC-121 (بک‌لاگ) |

---

## ۱. نمای کلی فازها

```
M0 ─────── M1 ─────── M2 ─────── M3 ─────── M4
تحلیل    هسته      وب        داده      امنیت/هویت
(این سند) (8 موتور) (8 موتور) (6 موتور) (7 موتور)
                                            │
M9 ◀───── M8 ◀───── M7 ◀───── M6 ◀───── M5
ابزار    دامنه     سرویس‌ها  زیرساخت    ارتباطی
(5 موتور) (3 موتور) (7 موتور) (9 موتور) 
```

| فاز | نام | موتورها | خروجی کلیدی | تخمین |
|---|---|---|---|---|
| **M0** | تحلیل و معماری | — | این مستندات | ✅ تکمیل |
| **M1** | هستهٔ صفر‌وابستگی | container, config, event, pipeline, error, log, compat, optimizer, module | یک Kernel که بوت می‌شود | ۲-۳ هفته |
| **M2** | وب | http, router, session, cookie, view, i18n, jalali, cors | Hello World + صفحهٔ وب | ۲-۳ هفته |
| **M3** | داده | database, schema, migration, orm, pagination, validation | CRUD کامل با DB | ۳-۴ هفته |
| **M4** | امنیت و هویت | crypt, hash, auth, rbac, security, cache, audit | ورود/نقش/امنیت | ۲-۳ هفته |
| **M5** | ارتباطی و زیرساخت | queue, schedule, console, filesystem, media, mail, notification, http-client, backup | Job/صف/ایمیل/فایل | ۳-۴ هفته |
| **M6** | سرویس‌ها | api, observability, search, tenancy, feature-flag, realtime | API/رصد/جست‌وجو | ۳ هفته |
| **M7** | دامنه | workflow, report, payment | BPM/گزارش/پرداخت | ۳-۴ هفته |
| **M8** | ابزار | scaffold, admin, plugin, test, upgrade | DX/پنل/تست | ۳ هفته |
| **M9** | اپلیکیشن‌ها و انتشار | kits | Blog/Shop/…ERP + Release 1.0 | ۴-۶ هفته |

---

## ۲. جزئیات هر فاز

### M1 — هستهٔ صفر‌وابستگی
**هدف:** یک Kernel که بدون هیچ وابستگی خارجی بوت می‌شود و Container/Config/Event کار می‌کنند.

**خروجی:**
- `core/bootstrap.php` + Autoloader خودنوشته
- Container با Auto-Wiring + Contextual + Lazy
- Config Repository + ENV Loader (بدون putenv)
- Event Dispatcher + Outbox
- Pipeline
- Error Handler + Exception Handler + Log
- Compat (Polyfillها)
- Optimizer (cache)
- Module Registry

**معیار پذیرش:**
```
✅ پروژه بدون vendor/ بالا می‌آید
✅ bin/tvn about کار می‌کند
✅ Container یک سرویس با ۵ وابستگی را Resolve می‌کند
✅ Event dispatch + Outbox roundtrip
✅ زمان بوت ≤ ۸ms (warm)
✅ همهٔ تست‌های M1 سبز + coverage ≥ ۹۵٪
```

### M2 — وب
**هدف:** یک درخواست HTTP کامل از ورود تا پاسخ.

**خروجی:**
- Request/Response Immutable + Kernel + Middleware
- Router با Compiled Trie + Route Cache
- Session (۵ درایور) + Cookie
- View Engine (.tv.php) با کامپایلر
- i18n + Jalali (تقویم دقیق)
- CORS

**معیار پذیرش:**
```
✅ Hello World وب با ≤ ۳ms سربار
✅ Route match ۱۰۰۰ روت ≤ ۵µs
✅ Session secure + CSRF-ready
✅ View با auto-escape (۰ XSS)
✅ تقویم جلالی ۱۰۰٪ دقیق
```

### M3 — داده
**هدف:** CRUD کامل با دیتابیس.

**خروجی:**
- Connection + Query Builder + Grammar (MySQL/PG/SQLite)
- Schema Builder + Migration
- ORM (Active Record + Repository)
- Pagination
- Validation (۸۵ قاعده)

**معیار پذیرش:**
```
✅ Query Builder معادل در ۳ درایور
✅ Migration up/down + lock
✅ ORM با رابطه‌ها + eager loading (۰ N+1)
✅ Validation با قواعد ایرانی
✅ ۰ SQL Injection در Fuzzing
```

### M4 — امنیت و هویت
**خروجی:** Crypt, Hash, Auth (Session/JWT/OTP/2FA), RBAC (Gate/Policy), Security (CSRF/RateLimit/SSRF/Sanitizer), Cache, Audit.

**معیار پذیرش:**
```
✅ ورود/خروج امن با session hardening
✅ JWT با algorithm pinning
✅ RBAC با row-level scope
✅ CSRF/RateLimit/Security Headers فعال
✅ Audit با hash chain
```

### M5 — ارتباطی و زیرساخت
**خروجی:** Queue, Schedule, Console, Filesystem, Media, Mail, Notification, HttpClient, Backup.

### M6 — سرویس‌ها
**خروجی:** API (Resource/OpenAPI), Observability, Search (فارسی), Tenancy, FeatureFlag, Realtime.

### M7 — دامنه
**خروجی:** Workflow (BPM), Report (xlsx خودنوشته), Payment (درگاه‌های ایرانی).

### M8 — ابزار
**خروجی:** Scaffold (make:*), Admin (CRUD generator), Plugin, Test framework, Upgrade.

### M9 — اپلیکیشن‌ها و انتشار
**خروجی:** کیت‌های Blog, News, Shop, Forum, LMS, CRM, ERP, BPMS + Release 1.0.

---

## ۳. وابستگی بین فازها

```
M1 (هسته) ← پیش‌نیاز همه
M2 (وب) ← M1
M3 (داده) ← M1
M4 (امنیت) ← M2, M3
M5 (زیرساخت) ← M1, M3
M6 (سرویس) ← M2, M3, M4
M7 (دامنه) ← M3, M4, M5, M6
M8 (ابزار) ← همه
M9 (اپ) ← همه
```
🔒 هیچ فازی قبل از پیش‌نیازهایش شروع نمی‌شود.
⚠️ برخی موتورها را می‌توان موازی ساخت (مثلاً M2 و M3 هر دو فقط به M1 نیاز دارند).

---

## ۴. استراتژی تحویل

| اصل | توضیح |
|---|---|
| **Vertical Slice** | هر فاز یک قابلیتسرتاسری قابل‌نمایش تحویل می‌دهد |
| **Test-First** | معیار پذیرش قبل از کد |
| **Docs-First** | مستند موتور قبل از پیاده‌سازی (DOC-015) |
| **Continuous Integration** | هر PR کامل سبز |
| **Incremental** | هر موتور مستقل merge می‌شود |

---

## ۵. ریسک‌ها و mitigation

| ریسک | احتمال | اثر | mitigation |
|---|---|---|---|
| پیچیدگی ORM زیاد | بالا | بالا | ADR-0007، شروع با Active Record ساده |
| دقت تقویم جلالی | متوسط | بالا | Test Vectors گسترده (DOC-070) |
| کارایی Route در مقیاس | متوسط | متوسط | Compiled Trie + cache |
| Scope creep در موتورها | بالا | متوسط | مرز روشن در DOC-030، YAGNI |
| سازگاری نسخه‌های PHP | متوسط | متوسط | Compat engine + CI matrix |
| امنیت درگاه‌های پرداخت | متوسط | بالا | verify از API، نه callback |

---

## ۶. تعریف «انتشار 1.0» (Release Criteria)

- [ ] همهٔ ۵۳ موتور پیاده و تست‌شده
- [ ] Coverage هسته ≥ ۹۵٪، موتورها ≥ ۹۰٪
- [ ] همهٔ اهداف کارایی (DOC-112) پاس
- [ ] صفر آسیب‌پذیری High/Critical
- [ ] همهٔ ۸ کیت اپلیکیشن نصب‌شدنی
- [ ] مستندات کامل (docs.tavaan.dev)
- [ ] `tvn` CLI کامل
- [ ] راهنمای مهاجرت + Quick Start
- [ ] Benchmark در برابر Laravel/Symfony (رقابتی)
