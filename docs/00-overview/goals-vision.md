# DOC-001 — اهداف کلان و چشم‌انداز تَوان

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-001 |
| وضعیت | Approved v1.0 |
| مخاطب | معماران، توسعه‌دهندگان، ایجنت‌های AI |
| پیش‌نیاز | — |
| خروجی کلیدی | ۷ هدف کلان (G1..G7) + ۱۴ هدف فرعی + KPI |

---

## ۱. بیانیهٔ مأموریت (Mission)

> ساخت یک **بستر نرم‌افزاری خودکفا و خودمختار** با PHP خالص که بتواند از یک وبلاگ شخصی تا یک
> **ERP/BPMS سازمانی** را بدون وابستگی به هیچ پکیج ثالث اجرا کند، بدون قربانی‌کردن امنیت، سرعت و
> تجربهٔ توسعه.

## ۲. بیانیهٔ چشم‌انداز (Vision) — افق ۳ ساله

1. **V1 — خودکفایی کامل:** هیچ `vendor/` خارجی؛ تنها `engines/` خودنوشته.
2. **V2 — یک فریمورک، چند محصول:** هستهٔ ثابت + ماژول‌های دامنه‌ای (Blog…ERP) به‌عنوان «کیت».
3. **V3 — قابل تجزیه:** همان کد، هم مونولیت ماژولار و هم مایکروسرویس (بدون بازنویسی).
4. **V4 — AI-Native:** هر جزء دارای مشخصات ماشین‌خوان تا ایجنت‌ها بتوانند توسعه/تست/بازبینی کنند.
5. **V5 — بومی‌سازی عمیق:** تقویم جلالی، RTL، درگاه‌های پرداخت ایرانی، شماره/کد ملی، فونت فارسی.

---

## ۳. اهداف کلان (Macro Goals)

### G1 — امنیت (Security-First) 🔒
| شناسه | هدف | شاخص اندازه‌گیری |
|---|---|---|
| G1.1 | حذف کامل دسته‌خطاهای OWASP Top 10 در لایهٔ هسته | ۰ آسیب‌پذیری High در SAST/DAST |
| G1.2 | امن‌سازی به‌صورت پیش‌فرض (Secure by Default) | همهٔ کنترل‌ها فعال بدون کانفیگ اضافه |
| G1.3 | رمزنگاری استاندارد و کلیدگردانی | Argon2id/AES-256-GCM، Rotation خودکار |
| G1.4 | قابلیت ردیابی و انکارناپذیری (Audit) | ۱۰۰٪ تغییرات حساس با زنجیرهٔ هش ثبت شود |

### G2 — سرعت و کارایی (Performance)
| شناسه | هدف | شاخص |
|---|---|---|
| G2.1 | زمان بوت هسته | ≤ ۸ms (opcache گرم)، ≤ ۲۵ms (سرد) |
| G2.2 | سربار هر درخواست نسبت به PHP خام | ≤ ۳ms در مسیر Hello-World |
| G2.3 | Route Matching | ≤ ۵µs در ۱۰۰۰ روت (Compiled Trie) |
| G2.4 | مصرف حافظه پایه | ≤ ۴MB در درخواست ساده |
| G2.5 | آمادگی برای Execution Modeهای مقیاس‌پذیر | Swoole/RoadRunner/FrankenPHP بدون تغییر کد کاربردی |

### G3 — دقت و صحت (Correctness)
| شناسه | هدف | شاخص |
|---|---|---|
| G3.1 | تایپ‌سازی کامل | ۱۰۰٪ `strict_types`، PHPStan level max بدون error |
| G3.2 | پوشش تست هسته | ≥ ۹۵٪ خط، ≥ ۹۰٪ شاخه، Mutation Score ≥ ۸۵٪ |
| G3.3 | صفر Deprecated در PHP 8.2/8.3/8.4 | خروجی خالی `E_DEPRECATED` در CI |
| G3.4 | رفتار قطعی (Deterministic) | تست‌ها بدون وابستگی به زمان/ترتیب تصادفی |

### G4 — خودکفایی و خودمختاری (Self-Sufficiency & Autonomy)
| شناسه | هدف | شاخص |
|---|---|---|
| G4.1 | Autoloader، Container، Router، ORM، Cache، Queue همه خودنوشته | ۰ پکیج composer در هسته |
| G4.2 | عدم شکست در غیاب افزونه‌های اختیاری PHP | Fallback داخلی برای هر extension |
| G4.3 | خودترمیمی (Self-Healing) | Cache/Config/Route خودکار بازسازی شود |
| G4.4 | خودپیکربندی (Self-Configuring) | مقادیر منطقی پیش‌فرض بدون نیاز به `.env` |

### G5 — راحتی و لذت توسعه (Developer Experience)
| شناسه | هدف | شاخص |
|---|---|---|
| G5.1 | زمان «سلام دنیا» تا اجرای اولین ماژول | ≤ ۵ دقیقه |
| G5.2 | Scaffold کامل (make:module/controller/model/migration/…) | هر موجودیت با یک دستور |
| G5.3 | خطاهای گویا با راه‌حل پیشنهادی | هر استثنا شامل `hint` و `docsUrl` |
| G5.4 | Debug Bar / Profiler درون‌ساخته | نمایش زمان، کوئری، حافظه، رویدادها |
| G5.5 | IDE-Friendly | Stub + `@mixin` برای Facade + `phpstorm.meta.php` |

### G6 — انعطاف معماری (Architectural Versatility)
| شناسه | هدف | شاخص |
|---|---|---|
| G6.1 | پوشش MVC / HMVC / Modular / ED / Layered / Hexagonal | همه به‌صورت همزمان و انتخاب‌پذیر |
| G6.2 | اجرای همزمان چند «کاربرد» در یک نصب | Multi-App در یک instance |
| G6.3 | تبدیل ماژول به سرویس مستقل بدون بازنویسی | فقط تغییر Adapter/Deployment |
| G6.4 | پشتیبانی از وب، CLI، Queue Worker، Realtime، Cron | یک هسته، چند Entry Point |

### G7 — مقیاس‌پذیری دامنه (Domain Scalability)
| شناسه | هدف | شاخص |
|---|---|---|
| G7.1 | ارائهٔ کیت‌های آماده: Blog, Shop, Forum, LMS, News | نصب با یک دستور |
| G7.2 | ارائهٔ کیت‌های سازمانی: ERP, BPMS, CRM, HRM | ماژول‌های قابل ترکیب |
| G7.3 | موتور دامنه‌ای Workflow/Report/Audit به‌عنوان زیرساخت مشترک | استفادهٔ مجدد ≥ ۸۰٪ بین کیت‌ها |

---

## ۴. اهداف غیر‌کلیدی (Anti-Goals) — کارهایی که تَوان **نمی‌کند**

| ضد‌هدف | دلیل |
|---|---|
| ❌ جایگزینی کامل JavaScript/فریمورک فرانت | تَوان فقط API + SSR ارائه می‌دهد؛ فرانت دلخواه است |
| ❌ ORM سنگین با Unit of Work کامل در نسخهٔ ۱ | پیچیدگی/کارایی؛ در فاز بعد با ADR |
| ❌ پشتیبانی از PHP < 8.2 | استفاده از readonly/enum/fiber و حذف کدهای منسوخ |
| ❌ ساخت DBMS یا Message Broker اختصاصی | فقط کلاینت/Adapter برای MySQL/Postgres/SQLite/Redis |
| ❌ پنهان‌سازی SQL در جعبهٔ سیاه | Query Log و Explain در دسترس توسعه‌دهنده |
| ❌ Magic بیش از حد (بدون trace) | هر Magic باید Debuggable و مستند باشد |

---

## ۵. شاخص‌های کلیدی عملکرد (KPI Dashboard)

```yaml
# machine-readable: tavaan.kpi.v1
kpi:
  security:
    sast_high_findings: 0
    dependency_external_count: 0
    audit_coverage_sensitive_actions: 1.0
  performance:
    boot_ms_p95: 8
    hello_world_overhead_ms_p95: 3
    route_match_us_p95: 5
    memory_peak_mb_hello: 4
    rps_hello_world_1vcpu: 3000        # با Swoole
  quality:
    phpstan_level: max
    strict_types_coverage: 1.0
    deprecated_notices: 0
    line_coverage_core: 0.95
    mutation_score_core: 0.85
  dx:
    time_to_first_module_minutes: 5
    scaffold_commands_coverage: 1.0
    docs_per_engine: 1.0
  architecture:
    module_boundary_violations: 0       # توسط ArchUnit-like checker
    engines_count_min: 45
```

---

## ۶. ذی‌نفعان و ارزش پیشنهادی

| ذی‌نفع | درد اصلی | ارزش تَوان |
|---|---|---|
| توسعه‌دهندهٔ مستقل | سنگینی Laravel/Symfony برای پروژهٔ کوچک | هستهٔ سبک، بوت < ۱۰ms |
| تیم محصول | قفل‌شدن (Vendor Lock-in) روی پکیج‌ها | صفر وابستگی، مالکیت کامل کد |
| سازمان دولتی/بانکی | الزام بومی‌سازی و ممیزی امنیتی | کد قابل ممیزی، Audit Engine، تقویم/زبان بومی |
| Integrator | نیاز به ERP/BPMS/CRM همزمان | ماژول‌های دامنه‌ای قابل ترکیب |
| ایجنت AI | ابهام در مشخصات | اسناد جزء‌به‌جزء + معیار پذیرش + بک‌لاگ ماشین‌خوان |

---

## ۷. موفقیت چگونه سنجیده می‌شود؟ (Definition of Success)

- **S1** یک نصب تازه بدون اینترنت (Offline) کاملاً کار کند (بدون `composer install`).
- **S2** اپلیکیشن فروشگاه با ۱۰۰٬۰۰۰ محصول و ۱۰۰۰ سفارش همزمان پایدار بماند.
- **S3** همان کد ماژول «سفارش» در حالت میکروسرویس مستقل مستقر شود.
- **S4** یک ایجنت AI بتواند فقط با خواندن `docs/` یک موتور جدید بسازد که CI را پاس کند.
- **S5** گزارش ممیزی امنیتی شخص ثالث بدون یافتهٔ High/Critical.

## ۸. ارجاعات

- اصول پیاده‌سازی این اهداف: `docs/00-overview/principles.md` (DOC-002)
- الزامات تفکیک‌شده: `docs/00-overview/requirements.md` (DOC-003)
- تصمیم‌های معماری مرتبط: `docs/adr/ADR-0001-*.md`
