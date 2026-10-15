# شاخص مستندات تَوان (Tavaan Docs Index)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-000 |
| وضعیت | Approved v1.0 |
| نقش | نقطهٔ ورود رسمی و نمای ناوبری همهٔ مستندات |

> نقطهٔ ورود رسمی تمام مستندات. هر فایل دارای **شناسهٔ یکتا (DOC-ID)**، **وضعیت**، **نسخه** و
> **وابستگی‌های مطالعه** است تا ایجنت‌ها بتوانند مسیر یادگیری را به‌صورت گراف پیمایش کنند.

## راهنمای نمادها

| نماد | معنا |
|---|---|
| 🔒 | الزامی (MUST) — نقض آن باعث ریجکت کد می‌شود |
| ⚠️ | توصیهٔ قوی (SHOULD) |
| 💡 | اختیاری/بهتر است (MAY) |
| 🧪 | دارای معیار تست/پذیرش |
| 🧠 | مخصوص ایجنت‌های هوش مصنوعی (Machine-Readable بخش دارد) |

---

## جدول کامل اسناد

| DOC-ID | سند | مسیر | نوع | پیش‌نیاز |
|---|---|---|---|---|
| DOC-000 | شاخص | `docs/README.md` | فهرست | — |
| DOC-001 | اهداف و چشم‌انداز | `docs/00-overview/goals-vision.md` | تحلیلی | — |
| DOC-002 | اصول بنیادین | `docs/00-overview/principles.md` | تحلیلی | DOC-001 |
| DOC-003 | الزامات | `docs/00-overview/requirements.md` | تحلیلی 🧪 | DOC-001 |
| DOC-004 | راهنمای شروع سریع | `docs/00-overview/quickstart.md` | راهنما 🧠 | DOC-001, DOC-010 |
| DOC-010 | قواعد PHP 8.2+ | `docs/01-standards/php82-strict.md` | استاندارد 🔒 | DOC-002 |
| DOC-011 | سبک کد | `docs/01-standards/coding-style.md` | استاندارد 🔒 | DOC-010 |
| DOC-012 | قواعد نام‌گذاری | `docs/01-standards/naming-conventions.md` | استاندارد 🔒 | DOC-011 |
| DOC-013 | مدیریت خطا | `docs/01-standards/error-handling.md` | استاندارد 🔒 | DOC-010 |
| DOC-014 | کاتالوگ قراردادها | `docs/01-standards/contracts-interfaces.md` | مرجع 🔒 | DOC-010 |
| DOC-015 | استاندارد مستندسازی | `docs/01-standards/documentation-standard.md` | استاندارد | DOC-011 |
| DOC-016 | سیاست امنیتی | `docs/01-standards/security-policy.md` | استاندارد 🔒 | DOC-002 |
| DOC-020 | نمای کلان معماری | `docs/02-architecture/overview.md` | معماری | DOC-002 |
| DOC-021 | لایه‌ای + HMVC | `docs/02-architecture/layered-hmvc.md` | معماری 🔒 | DOC-020 |
| DOC-022 | ماژولار مونولیت | `docs/02-architecture/modular-monolith.md` | معماری 🔒 | DOC-021 |
| DOC-023 | رویداد‌محور (ED) | `docs/02-architecture/event-driven.md` | معماری 🔒 | DOC-022 |
| DOC-024 | آمادگی مایکروسرویس | `docs/02-architecture/microservice-ready.md` | معماری | DOC-023 |
| DOC-025 | چرخهٔ عمر درخواست | `docs/02-architecture/request-lifecycle.md` | معماری 🔒 | DOC-020 |
| DOC-026 | کانتینر و DI | `docs/02-architecture/container-di.md` | معماری 🔒 | DOC-025 |
| DOC-027 | راه‌اندازی و Autoload | `docs/02-architecture/bootstrapping.md` | معماری 🔒 | DOC-026 |
| DOC-028 | چنداجاره‌ای | `docs/02-architecture/multi-tenancy.md` | معماری | DOC-022 |
| DOC-030 | رجیستری موتورها | `docs/03-engines/README.md` | مرجع 🔒 🧠 | DOC-020 |
| DOC-031…075 | مستند هر موتور | `docs/03-engines/*.md` | مشخصات 🔒 🧪 | DOC-030 |
| DOC-080 | ساختار پوشه/فایل | `docs/04-structure/folder-structure.md` | مرجع 🔒 | DOC-021 |
| DOC-081 | قرارداد فایل‌ها | `docs/04-structure/file-conventions.md` | استاندارد 🔒 | DOC-080 |
| DOC-082 | نگاشت کامپوزر | `docs/04-structure/composer-mapping.md` | مرجع 🔒 | DOC-080 |
| DOC-083 | مرجع کامل CLI (۱۰۵ دستور) | `docs/04-structure/cli-reference.md` | مرجع 🔒 | DOC-076 |
| DOC-085 | مرجع پیکربندی (config + ENV) | `docs/04-structure/config-reference.md` | مرجع 🔒 | DOC-032 |
| DOC-086 | کاتالوگ طرح پایگاه داده | `docs/04-structure/database-schema-catalog.md` | مرجع 🔒 🧠 | DOC-047 |
| DOC-090 | الگوی اپلیکیشن | `docs/05-applications/README.md` | راهنما | DOC-022 |
| DOC-091…098 | وبلاگ/فروشگاه/فروم/LMS/خبری/ERP/BPMS/CRM | `docs/05-applications/*.md` | بلوپرینت 🧪 | DOC-090 |
| DOC-100 | ماتریس نقش‌ها | `docs/06-roles-access/roles-matrix.md` | مرجع 🔒 | DOC-022 |
| DOC-101 | کاتالوگ مجوزها | `docs/06-roles-access/permission-catalog.md` | مرجع 🔒 🧠 | DOC-100 |
| DOC-102 | وظایف و گردش‌کار | `docs/06-roles-access/duties-workflow.md` | مرجع | DOC-100 |
| DOC-110 | استراتژی تست | `docs/07-quality/testing-strategy.md` | استاندارد 🔒 | DOC-030 |
| DOC-111 | تحلیل ایستا و CI | `docs/07-quality/static-analysis-ci.md` | استاندارد 🔒 | DOC-010 |
| DOC-112 | اهداف بنچمارک | `docs/07-quality/benchmark-targets.md` | استاندارد 🧪 | DOC-110 |
| DOC-113 | تعریف انجام‌شده | `docs/07-quality/definition-of-done.md` | استاندارد 🔒 | DOC-110 |
| DOC-120 | نقشهٔ راه | `docs/08-roadmap/milestones.md` | برنامه | DOC-030 |
| DOC-121 | بک‌لاگ ایجنت | `docs/08-roadmap/agent-backlog.md` | برنامه 🧠 | DOC-120 |
| DOC-130 | مراجع و بنچمارک‌ها | `docs/09-references/benchmarks.md` | پژوهشی | DOC-020 |
| DOC-131 | واژه‌نامه | `docs/09-references/glossary.md` | مرجع | — |
| DOC-140 | شاخص تصمیم‌های معماری (ADR) | `docs/adr/README.md` | تصمیم 🔒 | DOC-020 |
| DOC-150 | قالب مستند موتور | `docs/templates/engine-spec-template.md` | قالب | DOC-015 |
| DOC-151 | قالب ماژول | `docs/templates/module-template.md` | قالب | DOC-022 |
| DOC-152 | قالب ADR | `docs/templates/adr-template.md` | قالب | DOC-140 |
| — | 🧩 JSON Schemaها | `docs/schemas/*.json` | اعتبارسنجی 🧠 | DOC-022 |
| — | 🤖 فهرست ماشین‌خوان | `docs/manifest.json` | فهرست 🧠 | همه |

---

## منابع ماشین‌خوان (Machine-Readable Artifacts) 🧠

این فایل‌ها برای مصرف خودکار توسط ایجنت‌ها و ابزارها طراحی شده‌اند:

| فایل | قالب | کاربرد |
|---|---|---|
| `docs/manifest.json` | JSON | فهرست کامل همهٔ ۱۲۱ سند + ۵۳ موتور با DOC-ID، مسیر، hash و مسیرهای مطالعه — نقطهٔ ورود برنامه‌ای ایجنت |
| `docs/schemas/module.v1.json` | JSON Schema | اعتبارسنجی `module.json` هر ماژول |
| `docs/schemas/project.v1.json` | JSON Schema | اعتبارسنجی `tavaan.json` ریشهٔ پروژه |
| `docs/schemas/integration-event.v1.json` | JSON Schema | اعتبارسنجی قرارداد رویداد بین‌سرویسی |
| `docs/schemas/docs-manifest.v1.json` | JSON Schema | اعتبارسنجی خودِ `manifest.json` |

```bash
# بازتولید manifest پس از افزودن/ویرایش هر سند:
python3 tools/scripts/build-manifest.py
# اعتبارسنجی کل مجموعه (لینک‌ها، DOC-ID، JSON، همگامی manifest):
python3 tools/scripts/verify-docs.py
```

---

## مسیرهای مطالعه پیشنهادی (Learning Paths)

### ۱) مسیر ایجنت پیاده‌ساز موتور (Engine Builder Agent)
```
DOC-010 → DOC-011 → DOC-012 → DOC-013 → DOC-014 → DOC-030 → <engine-doc> → DOC-110 → DOC-113
```

### ۲) مسیر ایجنت معمار (Architect Agent)
```
DOC-001 → DOC-002 → DOC-020 → DOC-021 → DOC-022 → DOC-023 → DOC-024 → DOC-025 → ADRs
```

### ۳) مسیر ایجنت اپلیکیشن‌ساز (Application Builder Agent)
```
DOC-021 → DOC-022 → DOC-080 → DOC-090 → <app-doc> → DOC-100 → DOC-101
```

### ۴) مسیر بازبین/ممیز کیفیت (Reviewer / QA Agent)
```
DOC-010 → DOC-016 → DOC-110 → DOC-111 → DOC-112 → DOC-113
```

---

## قواعد حاکم بر این مستندات

1. 🔒 **منبع یگانهٔ حقیقت (SSOT):** اگر کد با سند در تضاد باشد، ابتدا سند اصلاح می‌شود (یا ADR ثبت) سپس کد.
2. 🔒 **شناسه‌پذیری:** هر الزام دارای شناسه است (مانند `NFR-SEC-04`) و در تست‌ها ارجاع داده می‌شود.
3. ⚠️ **بدون ابهام:** واژه‌های «باید/نباید» فقط با RFC 2119 معادل‌سازی می‌شوند: MUST/SHOULD/MAY.
4. ⚠️ **قابل‌اجرا:** هر سند موتور دارای بخش «معیار پذیرش» است که به تست تبدیل می‌شود.
5. 💡 **ایرانی/فارسی:** توضیحات فارسی، شناسه‌های فنی لاتین. دوزبانه بودن الزامی است (کلید + ترجمه).
