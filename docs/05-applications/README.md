# DOC-090 — الگوی ساخت اپلیکیشن (Application Blueprint)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-090 |
| والد | DOC-020، DOC-022 |
| نقش | الگوی مشترک همهٔ کیت‌ها/اپلیکیشن‌ها |

---

## ۱. مدل ساخت اپلیکیشن روی تَوان

هر «کاربرد» (وبلاگ، فروشگاه، ERP…) یک **کیت (Kit)** است که از چند **ماژول** + **پیکربندی** +
**Theme** + **دادهٔ اولیه** تشکیل می‌شود.

```
یک کاربرد = Kit
   ├── مجموعهٔ ماژول‌ها (modules/*)
   ├── Theme (resources/views + assets)
   ├── پیکربندی پیش‌فرض (presets/*.json)
   ├── دادهٔ نمونه (demo seed)
   └── نقش‌ها و مجوزهای از پیش تعریف‌شده
```

### ۱٫۱ سطح‌بندی کاربردها
| سطح | کاربرد | معماری | ماژول‌ها |
|---|---|---|---|
| **پایه** | وبلاگ، خبری، نمونه‌کار | `mvc`/`hmvc` | core-user + content |
| **متوسط** | فروشگاه، فروم، LMS | `modular` | + domain modules |
| **حرفه‌ای** | ERP، CRM، BPMS | `hexagonal` | + workflow/report/audit |
| **سازمانی** | پلتفرم یکپارچه | `hexagonal` + microservice | همه |

---

## ۲. ساختار یک Kit

```
kits/<kit>/
├── kit.json                 ← manifest کیت
├── README.md
├── presets/
│   ├── minimal.json         ← پیکربندی سبک
│   ├── standard.json
│   └── enterprise.json
├── modules/                 ← ماژول‌های اختصاصی کیت (یا symlink به modules/)
├── themes/
│   └── default/
│       ├── views/
│       ├── assets/{css,js}
│       └── theme.json
├── seed/
│   ├── demo/                ← دادهٔ نمونه
│   └── roles.json           ← نقش‌های پیش‌فرض
├── install/
│   ├── steps.json           ← گام‌های نصب
│   └── wizard/              ← نصب گرافیکی
└── docs/
```

### ۲٫۱ `kit.json`
```json
{
  "id": "tavaan.kit.shop",
  "title_fa": "کیت فروشگاه اینترنتی",
  "title_en": "E-Commerce Kit",
  "version": "1.0.0",
  "tavaan": "^1.0",
  "architecture": "modular",
  "description": "فروشگاه کامل با محصول، سبد، سفارش، پرداخت، موجودی و CRM سبک",

  "modules": [
    {"id": "tavaan.core-user", "required": true},
    {"id": "tavaan.tax",       "required": true},
    {"id": "tavaan.shop",      "required": true},
    {"id": "tavaan.payment",   "required": true},
    {"id": "tavaan.shipping",  "required": false},
    {"id": "tavaan.crm-lite",  "required": false},
    {"id": "tavaan.blog",      "required": false, "note": "وبلاگ محصول"}
  ],

  "engines": {
    "required": ["media","search","queue","mail","notification"],
    "recommended": ["payment","report","observability"]
  },

  "theme": "themes/default",
  "default_preset": "standard",

  "roles": ["customer","shop-operator","shop-manager","admin"],
  "navigation": "navigation.json",
  "demo_data": "seed/demo",

  "requirements": {
    "php": ">=8.2",
    "extensions": ["pdo","gd"],
    "storage_mb": 500,
    "memory_mb": 256
  },

  "install": {
    "steps": ["env","key:generate","migrate","seed:demo","optimize"],
    "wizard": true,
    "post_install": "shop:setup-wizard"
  },

  "health_check": "/health",
  "docs": "https://docs.tavaan.dev/kits/shop"
}
```

---

## ۳. گام‌های ساخت یک کاربرد

```
1. انتخاب معماری (mvc/hmvc/modular/hexagonal) بر اساس پیچیدگی دامنه
2. شناسایی ماژول‌ها (هر bounded context = یک ماژول)
3. طراحی Domain Model هر ماژول (Entity/VO/Aggregate/Event)
4. تعریف Contractهای عمومی بین ماژول‌ها
5. طراحی Schema دیتابیس (با prefix ماژول)
6. تعریف نقش‌ها و مجوزها (RBAC)
7. طراحی API (Resource/Route)
8. ساخت UI (View/Component/Theme)
9. تعریف رویدادها و جریان‌های بین‌ماژولی
10. نوشتن تست‌ها (Unit/Feature/E2E)
11. Seed دادهٔ نمونه
12. بسته‌بندی به‌عنوان Kit
```

---

## ۴. الگوی ماژول دامنه‌ای (یادآوری از DOC-022)

```
modules/<name>/
├── module.json
├── src/
│   ├── Contracts/          ← عمومی
│   ├── Http/               ← Presentation
│   ├── Application/        ← UseCase/Handler
│   ├── Domain/             ← Entity/VO/Service/Event
│   ├── Infrastructure/     ← Repository/Gateway
│   └── Providers/
├── config/ routes/ views/ lang/ migrations/ seeds/ tests/
```

---

## ۵. ماتریس ماژول‌ها در کاربردها

| ماژول | وبلاگ | خبری | فروشگاه | فروم | LMS | CRM | ERP | BPMS |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| core-user | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| content | ✅ | ✅ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| media | ✅ | ✅ | ✅ | ✅ | ✅ | ⬜ | ⬜ | ⬜ |
| comment | ✅ | ✅ | ✅ | ✅ | ✅ | ⬜ | ⬜ | ⬜ |
| shop | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| payment | ⬜ | ⬜ | ✅ | ⬜ | ✅ | ⬜ | ✅ | ⬜ |
| inventory | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ⬜ | ✅ | ⬜ |
| shipping | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| forum | ⬜ | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ⬜ | ⬜ |
| lms | ⬜ | ⬜ | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ⬜ |
| crm | ⬜ | ⬜ | lite | ⬜ | ⬜ | ✅ | ✅ | ⬜ |
| ticketing | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ✅ | ✅ | ✅ |
| workflow | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ✅ | ✅ |
| report | ⬜ | ⬜ | ✅ | ⬜ | ✅ | ✅ | ✅ | ✅ |
| accounting | ⬜ | ⬜ | lite | ⬜ | ⬜ | ⬜ | ✅ | ⬜ |
| hr | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ | ✅ | ⬜ |
| audit | ⬜ | ⬜ | ✅ | ⬜ | ⬜ | ✅ | ✅ | ✅ |
| notification | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| search | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⬜ |

✅ = الزامی • lite = نسخهٔ سبک • ⬜ = اختیاری/بدون نیاز

---

## ۶. قراردادهای مشترک بین ماژول‌ها

برای اینکه ماژول‌ها قابل ترکیب باشند، قراردادهای استانداردی تعریف می‌شود:

| Contract | ماژول ارائه‌دهنده | مصرف‌کنندگان |
|---|---|---|
| `UserReaderInterface` | core-user | همه |
| `MediaAttachableInterface` | media | shop, blog, lms |
| `CommentableInterface` | comment | blog, shop, lms |
| `PaymentRequestInterface` | payment | shop, lms, erp |
| `NotifiableInterface` | notification | همه |
| `SearchableInterface` | search | shop, blog, forum |
| `WorkflowSubjectInterface` | workflow | erp, bpms, crm |
| `ReportableInterface` | report | shop, erp, crm |
| `AuditableInterface` | audit | همه |

```php
// مثال: Contract مشترک برای موجودیت قابل پرداخت
interface PayableInterface
{
    public function payableAmount(): Money;
    public function payableDescription(): string;
    public function payableReference(): string;      // order_id, invoice_id
    public function markAsPaid(PaymentReference $ref): void;
}
```

---

## ۷. Theme و Appearance

```
themes/default/
├── theme.json           ← متادیتا + slotها
├── views/               ← override ویوهای پیش‌فرض
├── assets/
│   ├── css/app.css      ← با CSS Variables برای RTL/LTR
│   └── js/app.js
└── config/
    └── theme.php        ← رنگ‌ها، فونت، layout
```

```json
// theme.json
{
  "id": "default",
  "name": "پوستهٔ پیش‌فرض تَوان",
  "rtl": true,
  "font": "Vazirmatn",
  "colors": {"primary": "#2563eb", "secondary": "#64748b"},
  "slots": ["header","sidebar","footer"],
  "breakpoints": {"sm":640,"md":768,"lg":1024,"xl":1280}
}
```
🔒 Theme فقط View/Asset را override می‌کند؛ منطق دامنه دست‌نخورده می‌ماند.
🔒 RTL با CSS logical properties (`margin-inline-start` به‌جای `margin-left`).

---

## ۸. نصب یک Kit
```bash
tvn kit:install shop
# یا
tvn kit:install shop --preset=enterprise --with-demo
```
گام‌ها:
1. بررسی `requirements`
2. کپی/فعال‌سازی ماژول‌ها
3. اعمال preset
4. migrate + seed
5. نصب theme
6. optimize
7. اجرای wizard (اختیاری)

---

## ۹. معیار پذیرش 🧪
- AC-APP-01: `tvn kit:install shop` یک فروشگاه کامل و کاربردی می‌سازد.
- AC-APP-02: همهٔ ماژول‌های یک کیت با `arch:check` سازگارند (بدون نقض مرز).
- AC-APP-03: Contractهای مشترک بین ماژول‌ها رعایت می‌شود.
- AC-APP-04: Theme قابل override بدون تغییر کد ماژول.
- AC-APP-05: نصب با demo data در ≤ ۵ دقیقه.

---

## ۱۰. ارجاع به بلوپرینت‌های اختصاصی
| کاربرد | سند |
|---|---|
| وبلاگ | `docs/05-applications/blog.md` (DOC-091) |
| خبری | `docs/05-applications/news-portal.md` (DOC-092) |
| فروشگاه | `docs/05-applications/shop.md` (DOC-093) |
| فروم | `docs/05-applications/forum.md` (DOC-094) |
| LMS | `docs/05-applications/lms.md` (DOC-095) |
| CRM | `docs/05-applications/crm.md` (DOC-096) |
| ERP | `docs/05-applications/erp.md` (DOC-097) |
| BPMS | `docs/05-applications/bpms.md` (DOC-098) |
