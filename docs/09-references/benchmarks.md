# DOC-130 — مراجع و بنچمارک‌ها (References & Benchmarks)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-130 |
| نقش | تحلیل فریمورک‌ها/CMSها/پروژه‌های مرجع و درس‌هایی برای تَوان |

---

## ۱. چرا این مراجع؟

تَوان از صفر نوشته می‌شود اما **چرخ را دوباره اختراع نمی‌کند**. هر مرجع برای یک
«الگو» یا «مسئله» بررسی می‌شود؛ نه برای کپی‌کردن.

---

## ۲. فریمورک‌های PHP

### ۲٫۱ Laravel
| جنبه | نکته برای تَوان |
|---|---|
| **نقاط قوت** | DX عالی، Facade، Eloquent، Ecosystem غنی، Artisan |
| **نقاط ضعف (برای هدف ما)** | وابستگی سنگین به Composer/Symfony، سنگینی برای پروژهٔ کوچک، Magic زیاد |
| **چه را الهام بگیریم** | Fluent API، Facade با `@mixin`، Eloquent relations، Artisan commands، Pipeline |
| **چه را تغییر دهیم** | صفر وابستگی، سبک‌تر، Facade قابل‌ردیابی‌تر، بدون global helpers بیش از حد |
| **موتورهای معادل** | Eloquent→ORM، Blade→View، Artisan→Console، Events→Event |

### ۲٫۲ Symfony
| جنبه | نکته |
|---|---|
| **نقاط قوت** | معماری تمیز، Componentهای مستقل، DI قدرتمند، HttpFoundation عالی |
| **نقاط ضعف** | پیچیدگی، Curve یادگیری تند، Boilerplate |
| **الهام** | Component-based، Contracts، HttpFoundation (Request/Response)، DI Compiler، EventDispatcher |
| **تغییر** | سادگی بیشتر، بدون YAML/XML، DI با Reflection cache |
| **معادل** | HttpFoundation→Http، DI→Container، EventDispatcher→Event |

### ۲٫۳ CodeIgniter 4
| جنبه | نکته |
|---|---|
| **نقاط قوت** | سبک، ساده، سریع |
| **الهام** | سادگی، footprint کم |
| **تغییر** | معماری مدرن‌تر (DI، Contracts) |

### ۲٫۴ Yii
| جنبه | نکته |
|---|---|
| **نقاط قوت** | Gii (code generator)، ActiveRecord قوی |
| **الهام** | Scaffold/Gii→Scaffold engine |

### ۲٫۵ Slim / Laminas
| جنبه | نکته |
|---|---|
| **نقاط قوت** | Micro-framework، PSR compliance |
| **الهام** | PSR-7/15 مفاهیم، Middleware onion |

---

## ۳. سیستم‌های مدیریت محتوا (CMS)

### ۳٫۱ WordPress
| جنبه | نکته برای تَوان |
|---|---|
| **نقاط قوت** | سیستم Hook/Filter قدرتمند، Plugin ecosystem عظیم، Theme |
| **نقاط ضعف** | کد قدیمی، امنیت پلاگین‌ها، Performance، Global state |
| **الهام** | Hook/Filter→Event+Plugin، Theme→View override، Plugin system |
| **تغییر** | پلاگین با امضا+sandbox (نه هر کدی)، بدون global state، معماری مدرن |

### ۳٫۲ Drupal
| جنبه | نکته |
|---|---|
| **نقاط قوت** | معماری ماژولار قوی، Taxonomy، Permissions granular، Entity API |
| **الهام** | Module system→DOC-022، Permission→RBAC، Entity→ORM |
| **تغییر** | سادگی، بدون پیچیدگی بیش از حد Drupal |

### ۳٫۳ Joomla
| جنبه | نکته |
|---|---|
| **الهام** | MVC، Extension system |

---

## ۴. سیستم‌های سازمانی (ERP/CRM/BPMS)

### ۴٫۱ Odoo
| جنبه | نکته برای تَوان |
|---|---|
| **نقاط قوت** | ماژول‌بندی عالی (۴۰+ ماژول یکپارچه)، ORM قدرتمند، Views خودکار، Workflow |
| **الهام** | Module manifest→DOC-022، ORM، Admin CRUD generator→DOC-078، یکپارچگی بین ماژول با Event |
| **تغییر** | PHP به‌جای Python، صفر وابستگی |
| **کاربرد** | الگوی اصلی برای ERP تَوان (DOC-097) |

### ۴٫۲ ERPNext (Frappe)
| جنبه | نکته |
|---|---|
| **نقاط قوت** | DocType (metadata-driven)، Workflow engine، Report Builder |
| **الهام** | DocType→Admin CRUD، Workflow→DOC-071، Report Builder→DOC-072 |

### ۴٫۳ Dolibarr
| جنبه | نکته |
|---|---|
| **نقاط قوت** | ERP/CRM سبک PHP، ماژولار |
| **الهام** | ساختار ماژول ERP در PHP |

### ۴٫۴ SuiteCRM / EspoCRM
| جنبه | نکته |
|---|---|
| **نقاط قوت** | Entity-Relationship driven، Workflow |
| **الهام** | CRM architecture (DOC-096)، metadata-driven entities |

### ۴٫۵ Camunda / Flowable / Activiti
| جنبه | نکته |
|---|---|
| **نقاط قوت** | BPMN 2.0 استاندارد، Process engine قوی |
| **الهام** | مفاهیم BPMN (Gateway/Event/Task)→DOC-071/098 |
| **تغییر** | زیرمجموعهٔ کاربردی با JSON ساده (نه XML سنگین BPMN)، PHP-native |

---

## ۵. کتابخانه‌های تخصصی (که جایگزین‌شان می‌نویسیم)

| کتابخانه | کاربرد | جایگزین تَوان |
|---|---|---|
| `monolog/monolog` | Log | `engines/log` |
| `symfony/*` | همه‌چیز | هسته + موتورها |
| `guzzlehttp/guzzle` | HTTP Client | `engines/http-client` |
| `nesbot/carbon` | تاریخ | `engines/jalali` + `Support\Time` |
| `ramsey/uuid` | UUID | `Support\Id\Uuid/Ulid` |
| `firebase/php-jwt` | JWT | `engines/auth` (JwtEncoder) |
| `intervention/image` | تصویر | `engines/media` |
| `phpoffice/phpspreadsheet` | Excel | `engines/report` (XlsxWriter) |
| `vlucas/phpdotenv` | ENV | `engines/config` (EnvLoader) |
| `nikic/fast-route` | Router | `engines/router` (Compiled Trie) |
| `spatie/laravel-permission` | RBAC | `engines/rbac` |
| `phpmailer/phpmailer` | ایمیل | `engines/mail` (SMTP خالص) |
| `fzaninotto/faker` | Faker | `engines/test` (Faker خودنوشته) |
| `dragonmantank/cron-expression` | Cron | `engines/schedule` (CronExpression) |
| `league/flysystem` | Filesystem | `engines/filesystem` |
| `mtdowling/jmespath` | — | — |

---

## ۶. استانداردها و RFCها

| استاندارد | کاربرد در تَوان |
|---|---|
| **PSR-1/12** | سبک کد (DOC-011) |
| **PSR-4** | منطق Autoloader (پیاده‌سازی خودنوشته) |
| **PSR-3** | مفاهیم Logger (Interface داخلی) |
| **PSR-7/15** | مفاهیم Request/Response/Middleware |
| **PSR-11** | مفاهیم Container (Interface داخلی) |
| **RFC 2119** | سطوح MUST/SHOULD/MAY در مستندات |
| **RFC 3986** | URI parsing |
| **RFC 5424** | Syslog levels |
| **RFC 6238** | TOTP (2FA) |
| **RFC 6455** | WebSocket |
| **RFC 9457** | Problem Details (خطای JSON) |
| **RFC 5869** | HKDF |
| **CloudEvents 1.0** | ساختار Event Envelope |
| **W3C TraceContext** | Distributed Tracing |
| **OpenAPI 3.1** | مستندسازی API |
| **OWASP Top 10 / ASVS** | امنیت (DOC-016) |
| **SemVer 2.0** | نسخه‌گذاری |
| **BPMN 2.0** | مفاهیم Workflow |
| **NIST SP 800-38D/63B** | رمزنگاری/احراز هویت |

---

## ۷. بنچمارک مقایسه‌ای (اهداف)

| سنجه | Laravel | Symfony | CodeIgniter | **هدف تَوان** |
|---|---|---|---|---|
| زمان بوت (warm) | ~۱۵ms | ~۲۰ms | ~۵ms | **≤ ۸ms** |
| سربار Hello World | ~۵ms | ~۶ms | ~۲ms | **≤ ۳ms** |
| حافظهٔ پایه | ~۸MB | ~۱۰MB | ~۳MB | **≤ ۴MB** |
| RPS (Swoole, hello) | ~۲۵۰۰ | ~۲۰۰۰ | ~۳۰۰۰ | **≥ ۳۰۰۰** |
| Route match (۱۰۰۰) | ~۱۰µs | ~۱۵µs | ~۵µs | **≤ ۵µs** |
| تعداد پکیج Composer | ~۵۰+ | ~۳۰+ | ~۵ | **۰** |

> ⚠️ اعداد بالا تقریبی و برای مقایسهٔ نسبی‌اند. بنچمارک دقیق در DOC-112.

---

## ۸. درس‌های کلیدی از مراجع

| درس | منبع | اعمال در تَوان |
|---|---|---|
| ماژولار بودن با manifest | Odoo, Drupal | DOC-022 |
| Contract-first و DI | Symfony | DOC-014، DOC-026 |
| DX خوب با Facade/Artisan | Laravel | DOC-026، DOC-076 |
| Hook/Plugin قدرتمند | WordPress | DOC-079 (با sandbox) |
| امنیت پلاگین | (نقطهٔ ضعف WP) | امضا + scan + capability |
| سبکی | CodeIgniter, Slim | هستهٔ مینیمال (P3) |
| Metadata-driven CRUD | Frappe, Odoo | DOC-078 |
| BPM استاندارد | Camunda | DOC-071 (ساده‌شده) |
| تقویم بومی | jalaali-js | DOC-070 |

---

## ۹. منابع مطالعاتی برای تیم

| موضوع | منبع |
|---|---|
| معماری نرم‌افزار | "Clean Architecture" (Martin)، "Patterns of Enterprise Application Architecture" (Fowler) |
| DDD | "Domain-Driven Design" (Evans)، "Implementing DDD" (Vernon) |
| Microservices | "Building Microservices" (Newman)، microservices.io |
| امنیت | OWASP ASVS، "The Web Application Hacker's Handbook" |
| PHP | php.net/docs، "PHP 8 Objects, Patterns, and Practice" |
| Event-Driven | "Enterprise Integration Patterns"، martin-fowler.com |
| Refactoring | "Refactoring" (Fowler) |

---

## ۱۰. جمع‌بندی: جایگاه تَوان

```
             سادگی ↑
                    │  CodeIgniter   Slim
                    │                    ● Tavaan (هدف)
                    │              Laravel
                    │  Yii                    Symfony
                    │
                    └──────────────────────────→ قدرت/انعطاف
```

تَوان می‌خواهد **سادگی CodeIgniter** + **قدرت Laravel/Symfony** + **ماژولار بودن Odoo/Drupal**
را با **صفر وابستگی** و **بومی‌سازی عمیق** ترکیب کند.
