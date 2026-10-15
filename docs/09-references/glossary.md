# DOC-131 — واژه‌نامه (Glossary) 📖

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-131 |
| نقش | یکسان‌سازی واژگان فارسی↔لاتین در همهٔ مستندات و کد |
| قاعده | در متن فارسی از معادل فارسی، در کد/شناسه از لاتین |

---

## ۱. واژگان معماری

| فارسی | لاتین | تعریف |
|---|---|---|
| هسته | Kernel | بخش اجرایی حداقلی فریمورک |
| موتور | Engine | کتابخانهٔ خودنوشتهٔ یک قابلیت |
| ماژول | Module | واحد خودبسندهٔ دامنه‌ای (HMVC) |
| کیت | Kit | مجموعهٔ ماژول‌ها برای یک کاربرد |
| افزونه | Plugin | کد ثالث (معاف از صفر‌وابستگی) |
| لایه | Layer | سطح معماری (Presentation/Domain/…) |
| قرارداد | Contract | Interface عمومی |
| وابستگی | Dependency | نیاز یک جزء به جزء دیگر |
| تزریق وابستگی | Dependency Injection (DI) | تأمین وابستگی از بیرون |
| ظرف | Container | مدیر ساخت و تزریق وابستگی‌ها |
| ارائه‌دهنده | Provider | کلاس ثبت‌کنندهٔ سرویس‌ها |
| مرز | Boundary | خط جداسازی ماژول‌ها |
| نقطهٔ توسعه | Extension Point | Hook/Contract/Decorator |
| نمای C4 | C4 Model | سطوح Context/Container/Component/Code |
| شش‌ضلعی | Hexagonal | معماری Ports & Adapters |
| دامنه | Domain | منطق کسب‌وکار خالص |
| زیرساخت | Infrastructure | پیاده‌سازی فنی |
| کاربرد | Application | لایهٔ orchestration |
| ارائه | Presentation | لایهٔ UI/Controller |

---

## ۲. واژگان الگوهای طراحی

| فارسی | لاتین |
|---|---|
| رویداد | Event |
| شنونده | Listener |
| مشترک | Subscriber |
| پخش‌کننده | Dispatcher |
| خط لوله | Pipeline |
| میان‌افزار | Middleware |
| نماینده | Proxy |
| تزئین‌کننده | Decorator |
| کارخانه | Factory |
| سازنده | Builder |
| مدیر | Manager |
| انبار | Repository |
| درگاه | Gateway |
| نگاشت‌کننده | Mapper |
| تطبیق‌دهنده | Adapter |
| چندریختی | Polymorphism |
| موجودیت | Entity |
| شیء مقدار | Value Object |
| ریشهٔ تجمع | Aggregate Root |
| سرویس دامنه | Domain Service |
| مورد کاربرد | Use Case |
| فرمان | Command |
| پرسش | Query |
| پردازشگر | Handler |
| شیء انتقال داده | DTO |

---

## ۳. واژگان داده

| فارسی | لاتین |
|---|---|
| پایگاه داده | Database |
| جدول | Table |
| ستون | Column |
| ردیف | Row |
| کلید اصلی | Primary Key |
| کلید خارجی | Foreign Key |
| ایندکس | Index |
| کوئری | Query |
| سازندهٔ کوئری | Query Builder |
| دستور زبان | Grammar |
| تراکنش | Transaction |
| نقطهٔ ذخیره | Savepoint |
| بن‌بست | Deadlock |
| مهاجرت | Migration |
| ساختار | Schema |
| دادهٔ اولیه | Seed |
| کارخانه (تست) | Factory |
| نرم‌حذف | Soft Delete |
| دامنه | Scope |
| آبشاری | Cascade |
| بهینه‌ساز | Optimizer |

---

## ۴. واژگان وب

| فارسی | لاتین |
|---|---|
| درخواست | Request |
| پاسخ | Response |
| مسیریاب | Router |
| روت | Route |
| کنترل‌کننده | Controller |
| نما | View |
| قالب | Template |
| چیدمان | Layout |
| بخش | Section |
| مؤلفه | Component |
| ویجت | Widget |
| کوکی | Cookie |
| نشست | Session |
| سرریز | Overflow |
| میان‌بر | Shortcut |
| بارگذاری | Loading |
| بازگردانی | Redirect |

---

## ۵. واژگان امنیت

| فارسی | لاتین |
|---|---|
| احراز هویت | Authentication |
| کنترل دسترسی | Authorization |
| نقش | Role |
| مجوز | Permission |
| سیاست | Policy |
| دروازه | Gate |
| رمزنگاری | Encryption |
| درهم‌سازی | Hashing |
| توکن | Token |
| کلید | Key |
| گواهی | Certificate |
| امضا | Signature |
| جعل درخواست | CSRF |
| اسکریپت بین‌سایتی | XSS |
| تزریق | Injection |
| عبور از مسیر | Path Traversal |
| جعل درخواست سمت سرور | SSRF |
| پنهان‌سازی | Masking |
| ممیزی | Audit |
| محدودسازی نرخ | Rate Limiting |
| انکارناپذیری | Non-repudiation |
| تفویض اختیار | Delegation |
| چهارچشمی | Four-Eyes |
| حداقل مجوز | Least Privilege |

---

## ۶. واژگان زیرساخت

| فارسی | لاتین |
|---|---|
| صف | Queue |
| کار | Job |
| کارگر | Worker |
| زمان‌بندی | Schedule |
| حافظهٔ نهان | Cache |
| گزارش | Log |
| رصدپذیری | Observability |
| سنجه | Metric |
| ردیابی | Trace |
| بازه | Span |
| سلامت | Health |
| پشتیبان | Backup |
| بازیابی | Restore |
| استقرار | Deployment |
| مقیاس‌پذیری | Scalability |
| دسترس‌پذیری | Availability |
| مقاومت | Resiliency |
| مدارشکن | Circuit Breaker |
| تکرار | Retry |
| برگشت | Backoff |
| صف مرده | Dead Letter Queue (DLQ) |
| صندوق خروجی | Outbox |
| صندوق ورودی | Inbox |
| کلید یکتایی | Idempotency Key |

---

## ۷. واژگان چنداجاره‌ای

| فارسی | لاتین |
|---|---|
| اجاره‌کننده | Tenant |
| چنداجاره‌ای | Multi-Tenancy |
| زمینه | Context |
| سهمیه | Quota |
| سطح ردیف | Row-Level |
| تفکیک | Isolation |
| تجهیز | Provisioning |

---

## ۸. واژگان کیفی

| فارسی | لاتین |
|---|---|
| تست واحد | Unit Test |
| تست ویژگی | Feature Test |
| تست یکپارچگی | Integration Test |
| تست انتها‌به‌انتها | E2E Test |
| پوشش کد | Code Coverage |
| جهش | Mutation |
| تحلیل ایستا | Static Analysis |
| معیار پذیرش | Acceptance Criteria |
| تعریف انجام‌شده | Definition of Done |
| رگرسیون | Regression |
| بنچمارک | Benchmark |
| بازبینی کد | Code Review |

---

## ۹. واژگان بومی/فارسی

| فارسی | لاتین | توضیح |
|---|---|---|
| جلالی | Jalali | تقویم هجری شمسی |
| قمری | Hijri | تقویم هجری قمری |
| نوروز | Nowruz | آغاز سال جلالی |
| کبیسه | Leap | سال ۳۶۶ روزه |
| نیم‌فاصله | ZWNJ | فاصلهٔ مجازی (می‌شود) |
| ارقام فارسی | Persian Digits | ۰۱۲۳۴۵۶۷۸۹ |
| شبا | SHEBA/IBAN | شمارهٔ حساب بانکی ایران |
| کد ملی | National Code | شناسهٔ ملی ایران |
| ریال/تومان | RIAL/Toman | واحد پول |
| راست‌به‌چپ | RTL | جهت متن |

---

## ۱۰. سرواژه‌ها (Acronyms)

| سرواژه | باز شده |
|---|---|
| HMVC | Hierarchical Model-View-Controller |
| DI | Dependency Injection |
| IoC | Inversion of Control |
| ORM | Object-Relational Mapping |
| DTO | Data Transfer Object |
| CRUD | Create/Read/Update/Delete |
| RBAC | Role-Based Access Control |
| ABAC | Attribute-Based Access Control |
| CQRS | Command Query Responsibility Segregation |
| ED/EDA | Event-Driven (Architecture) |
| BPMS | Business Process Management System |
| BPMN | Business Process Model and Notation |
| ERP | Enterprise Resource Planning |
| CRM | Customer Relationship Management |
| LMS | Learning Management System |
| GL | General Ledger |
| SLA | Service Level Agreement |
| RACI | Responsible/Accountable/Consulted/Informed |
| JWT | JSON Web Token |
| OTP | One-Time Password |
| TOTP | Time-based OTP |
| CSRF | Cross-Site Request Forgery |
| XSS | Cross-Site Scripting |
| SSRF | Server-Side Request Forgery |
| IDOR | Insecure Direct Object Reference |
| PII | Personally Identifiable Information |
| SSE | Server-Sent Events |
| WS | WebSocket |
| OTLP | OpenTelemetry Protocol |
| SBOM | Software Bill of Materials |
| SoD | Separation of Duties |
| ADR | Architecture Decision Record |
| SSOT | Single Source of Truth |
| TTL | Time To Live |
| LOC | Lines of Code |

---

## ۱۱. قواعد استفاده

1. 🔒 در متن فارسی، اولین بار: `واژهٔ فارسی (لاتین)` — بعد فقط فارسی.
2. 🔒 در کد/شناسه/کلید: فقط لاتین (snake_case یا PascalCase).
3. ⚠️ واژه‌های جدید باید به این واژه‌نامه افزوده شوند.
4. 💡 اگر معادل فارسی رایج نیست، همان لاتین بماند (مثل Cache→حافظهٔ نهان یا کش).
