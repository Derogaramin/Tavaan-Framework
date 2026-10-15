# DOC-100 — ماتریس نقش‌ها و سطوح دسترسی (Roles & Access Matrix) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-100 |
| موتور | `rbac` (DOC-057) |
| مرتبط | DOC-101 (کاتالوگ مجوز)، DOC-102 (وظایف)، DOC-016 (SEC-AC) |

---

## ۱. دو دسته نقش

تَوان دو لایهٔ نقش دارد:

| لایه | نقش | هدف |
|---|---|---|
| **سطح سیستم (System Roles)** | super-admin, admin, developer, auditor | مدیریت خودِ پلتفرم |
| **سطح کسب‌وکار (Business Roles)** | customer, shop-manager, editor, accountant, … | عملیات دامنه |

### ۱٫۱ اصل تفکیک وظایف (Separation of Duties) 🔒
```
super-admin ≠ developer ≠ auditor
- کسی که کد می‌نویسد (developer) نباید در production داده تغییر دهد
- کسی که audit می‌کند (auditor) نباید بتواند داده تغییر دهد (فقط خواندن)
- تغییر نقش حساس نیازمند تأیید دوم (four-eyes) است
```

---

## ۲. نقش‌های سطح سیستم

### ۲٫۱ Super Admin
| ویژگی | مقدار |
|---|---|
| کلید | `super-admin` |
| سطح | نامحدود (bypass gate) |
| دسترسی | همهٔ ماژول‌ها، تنظیمات، نقش‌ها، tenantها |
| محدودیت | 🔒 2FA اجباری، Audit همهٔ اعمال، four-eyes برای تغییرات حساس |
| تعداد | حداقل ممکن (۱-۲ نفر) |

### ۲٫۲ Admin
| ویژگی | مقدار |
|---|---|
| کلید | `admin` |
| سطح | بالا (بدون bypass) |
| دسترسی | مدیریت ماژول‌های اختصاص‌داده‌شده، کاربران، محتوا |
| محدودیت | بدون دسترسی به تنظیمات هسته/امنیت، Audit |

### ۲٫۳ Developer
| ویژگی | مقدار |
|---|---|
| کلید | `developer` |
| دسترسی | محیط توسعه، staging، دیباگ، migration (نه production data) |
| محدودیت | 🔒 در production فقط خواندن + دیباگ؛ بدون دسترسی به دادهٔ حساس |

### ۲٫۴ Auditor
| ویژگی | مقدار |
|---|---|
| کلید | `auditor` |
| دسترسی | فقط خواندن + Audit log + گزارش |
| محدودیت | 🔒 هیچ نوشتن/تغییر؛ immutability |

### ۲٫۵ Support
| ویژگی | مقدار |
|---|---|
| کلید | `support` |
| دسترسی | تیکت، مشاهدهٔ محدود دادهٔ کاربر (با mask)، reset رمز |
| محدودیت | بدون دسترسی به تنظیمات، Audit همهٔ مشاهده‌ها |

---

## ۳. نقش‌های سطح کسب‌وکار (به تفکیک کاربرد)

### ۳٫۱ مشترک (همهٔ کاربردها)
| نقش | کلید | دسترسی |
|---|---|---|
| مهمان | `guest` | محتوای عمومی |
| کاربر | `user` | پروفایل خود، عملیات پایه |
| مشتری | `customer` | خرید/سفارش خود |
| مدیر محتوا | `content-manager` | CRUD محتوا |
| ویراستار | `editor` | ویرایش + تأیید محتوا |

### ۳٫۲ فروشگاه (DOC-093)
| نقش | کلید | دسترسی |
|---|---|---|
| اپراتور فروشگاه | `shop-operator` | محصول، مشاهدهٔ سفارش |
| مدیر فروشگاه | `shop-manager` | + وضعیت سفارش، استرداد، گزارش |
| انباردار | `inventory-keeper` | موجودی، رسید/حواله |
| پشتیبان فروش | `sales-support` | سفارش مشتری، پاسخ |

### ۳٫۳ وبلاگ/خبری (DOC-091/092)
| نقش | کلید | دسترسی |
|---|---|---|
| نویسنده | `author`/`reporter` | محتوای خود، submit |
| ویراستار | `editor` | review/approve همه |
| سردبیر | `chief-editor` | انتشار، breaking، دسته |

### ۳٫۴ LMS (DOC-095)
| نقش | کلید | دسترسی |
|---|---|---|
| دانشجو | `student` | ثبت‌نام، محتوای خود، آزمون |
| مدرس | `instructor` | دورهٔ خود، نمره‌دهی |
| دستیار | `assistant` | کمک نمره‌دهی |
| مدیر آموزش | `lms-admin` | همهٔ دوره‌ها، گواهی |

### ۳٫۵ CRM (DOC-096)
| نقش | کلید | دسترسی |
|---|---|---|
| کارشناس فروش | `sales-agent` | سرنخ/فرصت خود |
| مدیر فروش | `sales-manager` | تیم، گزارش، assign |
| کارشناس پشتیبانی | `support-agent` | تیکت assigned |
| مدیر پشتیبانی | `support-manager` | همهٔ تیکت‌ها، SLA |
| بازاریاب | `marketing` | کمپین، مخاطب |

### ۳٫۶ ERP (DOC-097)
| نقش | کلید | دسترسی |
|---|---|---|
| حسابدار | `accountant` | ثبت/تأیید سند |
| مدیر مالی | `finance-manager` | بستن دوره، گزارش مالی |
| انباردار | `warehouse-keeper` | رسید/حواله |
| کارپرداز | `procurement-officer` | خرید |
| کارشناس فروش | `sales-officer` | فروش |
| کارشناس HR | `hr-officer` | پرسنل/حقوق |
| برنامه‌ریز تولید | `production-planner` | تولید |
| حسابرس | `auditor` | فقط خواندن + audit |

### ۳٫۷ BPMS (DOC-098)
| نقش | کلید | دسترسی |
|---|---|---|
| فرایندساز | `process-designer` | طراحی فرایند/فرم |
| مدیر فرایند | `process-admin` | مدیریت نمونه‌ها |
| انجام‌دهنده | `task-assignee` | taskهای assigned |
| پایشگر | `monitor` | داشبورد (خواندن) |

---

## ۴. ماتریس دسترسی (نمونهٔ فروشگاه)

| عملیات | guest | customer | operator | manager | admin |
|---|:--:|:--:|:--:|:--:|:--:|
| مشاهدهٔ محصول | ✅ | ✅ | ✅ | ✅ | ✅ |
| افزودن به سبد | ✅ | ✅ | ✅ | ✅ | ✅ |
| ثبت سفارش | ❌ | ✅ | ✅ | ✅ | ✅ |
| مشاهدهٔ سفارش خود | ❌ | ✅ | ❌ | ❌ | ❌ |
| مشاهدهٔ همهٔ سفارش‌ها | ❌ | ❌ | ✅ | ✅ | ✅ |
| تغییر وضعیت سفارش | ❌ | ❌ | ⚠️ | ✅ | ✅ |
| استرداد | ❌ | ❌ | ❌ | ✅ | ✅ |
| ایجاد/ویرایش محصول | ❌ | ❌ | ✅ | ✅ | ✅ |
| حذف محصول | ❌ | ❌ | ❌ | ✅ | ✅ |
| مدیریت موجودی | ❌ | ❌ | ✅ | ✅ | ✅ |
| گزارش فروش | ❌ | ❌ | ❌ | ✅ | ✅ |
| مدیریت تخفیف | ❌ | ❌ | ❌ | ✅ | ✅ |
| مدیریت نقش‌ها | ❌ | ❌ | ❌ | ❌ | ✅ |

✅ = مجاز • ⚠️ = محدود • ❌ = غیرمجاز

---

## ۵. سطوح دسترسی داده (Data Access Levels)

علاوه بر «عملیات»، سطح «داده» هم کنترل می‌شود:

| سطح | توضیح | مثال |
|---|---|---|
| **Own** | فقط رکوردهای خود | سفارش‌های خود کاربر |
| **Team** | رکوردهای تیم خود | سرنخ‌های تیم فروش |
| **Department** | رکوردهای واحد | همهٔ تیکت‌های پشتیبانی |
| **Tenant** | همهٔ رکوردهای tenant | در حالت چنداجاره‌ای |
| **Global** | همهٔ رکوردها | super-admin |

```php
// در Policy
public function viewAny(User $user): bool
{
    return $user->dataLevel('shop.order') !== DataLevel::None;
}

// Scope خودکار
final class DataLevelScope implements ScopeInterface
{
    public function apply(QueryBuilder $q, Model $m): void
    {
        $level = Auth::user()->dataLevel($m->permissionPrefix());
        match ($level) {
            DataLevel::Own  => $q->where($m->getOwnerColumn(), Auth::id()),
            DataLevel::Team => $q->whereIn($m->getOwnerColumn(), Auth::user()->teamMemberIds()),
            DataLevel::Global => $q,   // بدون محدودیت
            default => $q->whereRaw('1=0'),   // None → هیچ
        };
    }
}
```

---

## ۶. سلسله‌مراتب نقش (Role Hierarchy)

```
super-admin
  └── admin
        ├── shop-manager
        │     └── shop-operator
        │           └── inventory-keeper
        ├── content-manager
        │     └── editor
        │           └── author
        └── support-manager
              └── support-agent
```
🔒 نقش والد، مجوزهای فرزندان را **ارث می‌برد** (نه برعکس).
🔒 حداکثر عمق سلسله‌مراتب: ۵ (جلوگیری از پیچیدگی).

---

## ۷. نقش‌های موقت و تفویض (Delegation)

```php
// نقش موقت با انقضا
$user->assignRole('shop-manager', expiresAt: now()->addDays(30));

// تفویض اختیار (مثلاً در مرخصی)
Delegation::create([
    'from' => $manager, 'to' => $assistant,
    'permissions' => ['shop.order.update','shop.order.refund'],
    'starts_at' => now(), 'ends_at' => now()->addDays(7),
    'reason' => 'مرخصی مدیر',
]);
```
🔒 هر دو در Audit ثبت می‌شوند.
🔒 تفویض نمی‌تواند از اختیارات خودِ فرد فراتر رود.

---

## ۸. مجوزهای سیستمی (System Permissions)

| کلید | توضیح | نقش پیش‌فرض |
|---|---|---|
| `system.settings.manage` | تنظیمات هسته | super-admin |
| `system.modules.manage` | نصب/حذف ماژول | super-admin |
| `system.users.manage` | مدیریت کاربران | admin |
| `system.roles.manage` | مدیریت نقش‌ها/مجوزها | super-admin |
| `system.audit.view` | مشاهدهٔ audit | auditor |
| `system.audit.export` | خروجی audit | auditor (با تأیید) |
| `system.backup.manage` | پشتیبان‌گیری | super-admin |
| `system.maintenance.toggle` | حالت تعمیر | super-admin |
| `system.cache.flush` | پاک‌کردن کش | admin |
| `system.logs.view` | مشاهدهٔ لاگ | admin, auditor |
| `system.debug` | دیباگ/ profiler | developer |

---

## ۹. اصول دسترسی 🔒

| # | اصل |
|---|---|
| 1 | **Deny by Default** — هیچ دسترسی بدون مجوز صریح |
| 2 | **Least Privilege** — حداقل مجوز لازم برای هر نقش |
| 3 | **Separation of Duties** — تفکیک author/approver/auditor |
| 4 | **Need to Know** — دسترسی داده فقط به اندازهٔ نیاز |
| 5 | **Four-Eyes** — تغییرات حساس با تأیید دوم |
| 6 | **Time-Bound** — نقش‌های حساس موقت با انقضا |
| 7 | **Auditable** — همهٔ تغییرات دسترسی در Audit |
| 8 | **Reviewable** — بازبینی دوره‌ای دسترسی‌ها (access review) |

---

## ۱۰. بازبینی دسترسی (Access Review)

```bash
tvn rbac:review --role=shop-manager     # چه کسانی این نقش را دارند؟
tvn rbac:orphans                        # مجوزهای بدون نقش
tvn rbac:stale                          # نقش‌های منقضی‌شده
tvn rbac:export --format=csv            # برای ممیزی
```
🔒 گزارش دوره‌ای (ماهانه) برای نقش‌های حساس.

---

## ۱۱. معیار پذیرش 🧪
- AC-ROLE-01: همهٔ نقش‌های §2-3 در seed اولیه تعریف می‌شوند.
- AC-ROLE-02: ماتریس §4 با Policy پیاده و تست می‌شود.
- AC-ROLE-03: Data Level Scope نشت داده = ۰.
- AC-ROLE-04: تفویض موقت پس از انقضا خودکار باطل می‌شود.
- AC-ROLE-05: هیچ نقش حساسی بدون 2FA (برای super-admin).
