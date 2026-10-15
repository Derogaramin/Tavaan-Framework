# DOC-102 — وظایف و گردش‌کار نقش‌ها (Duties & Workflow)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-102 |
| مرتبط | DOC-100 (نقش‌ها)، DOC-071 (Workflow)، DOC-057 (RBAC) |

---

## ۱. هدف
تعریف **وظایف (Duties)** هر نقش و **گردش‌کارهای تصویب (Approval Workflows)** که
چگونگی واگذاری و اجرای وظایف را مشخص می‌کند.

> تفاوت «مجوز» و «وظیفه»:
> - **مجوز (Permission):** *می‌تواند* انجام دهد (توانایی فنی)
> - **وظیفه (Duty):** *باید* انجام دهد (مسئولیت کسب‌وکار)

---

## ۲. ساختار وظیفه

```php
final readonly class Duty
{
    public function __construct(
        public string $key,              // 'shop.order.fulfillment'
        public string $titleFa,
        public array $requiredPermissions,   // مجوزهای لازم
        public array $roles,             // نقش‌های مسئول
        public ?string $sla,             // 'P1D' (یک روز)
        public ?string $escalation,      // نقش بالاتر در صورت تأخیر
        public bool $requiresApproval,   // نیازمند تأیید
        public ?string $approvalChain,   // 'manager,finance'
    ) {}
}
```

---

## ۳. وظایف به تفکیک نقش (نمونهٔ فروشگاه)

### ۳٫۱ shop-operator (اپراتور فروشگاه)
| وظیفه | مجوز لازم | SLA | تأیید |
|---|---|---|---|
| ثبت/ویرایش محصول | `shop.product.create/update` | — | ❌ |
| بررسی سفارش‌های جدید | `shop.order.view-any` | ۲ ساعت | ❌ |
| آماده‌سازی سفارش برای ارسال | `shop.order.status` | ۲۴ ساعت | ❌ |
| پاسخ به سؤال مشتری | `shop.order.view` | ۴ ساعت | ❌ |
| به‌روزرسانی موجودی | `shop.inventory.manage` | — | ⚠️ (اگر تعدیل منفی) |

### ۳٫۲ shop-manager (مدیر فروشگاه)
| وظیفه | مجوز لازم | SLA | تأیید |
|---|---|---|---|
| تأیید تخفیف‌های بزرگ | `shop.discount.manage` | — | ✅ (four-eyes) |
| استرداد سفارش | `shop.order.refund` | ۲۴ ساعت | ✅ (مالی) |
| بررسی گزارش فروش | `shop.report.sales` | هفتگی | ❌ |
| مدیریت نقش اپراتورها | `system.roles.assign` | — | ✅ |
| رسیدگی به شکایات | `shop.order.update` | ۴۸ ساعت | ❌ |

### ۳٫۳ inventory-keeper (انباردار)
| وظیفه | مجوز لازم | SLA | تأیید |
|---|---|---|---|
| ثبت رسید انبار | `shop.inventory.manage` | — | ❌ |
| حوالهٔ سفارش | `shop.inventory.manage` | ۲۴ ساعت | ❌ |
| انبارگردانی دوره‌ای | `shop.inventory.adjust` | ماهانه | ✅ (مالی) |

---

## ۴. گردش‌کارهای تصویب (Approval Workflows)

### ۴٫۱ الگوی کلی
```
درخواست (Request)
  → بررسی خودکار (Guard: مبلغ، نوع)
  → سطح ۱: تأیید سرپرست
  → سطح ۲ (اگر مبلغ > آستانه): تأیید مدیر
  → سطح ۳ (اگر مبلغ > آستانهٔ بالا): تأیید مالی/مدیرعامل
  → اجرا (Action)
  → ثبت در Audit
```

### ۴٫۲ تعریف با موتور Workflow
```php
Workflow::define('refund-approval', function (ProcessBuilder $p): void {
    $p->start('requested');

    $p->exclusiveGateway('amount_check');
    $p->transition('submit', from: 'requested', to: 'amount_check');

    // استرداد < ۵M ریال → تأیید خودکار مدیر فروشگاه
    $p->transition('auto', from: 'amount_check', to: 'manager_approve')
        ->guard(fn ($c) => $c->get('amount') < 5_000_000);

    // استرداد ≥ ۵M → تأیید مالی + مدیر
    $p->transition('escalate', from: 'amount_check', to: 'finance_approve')
        ->guard(fn ($c) => $c->get('amount') >= 5_000_000);

    $p->state('finance_approve', type: StateType::UserTask)
        ->assignee('role:finance-manager')->next('manager_approve');
    $p->state('manager_approve', type: StateType::UserTask)
        ->assignee('role:shop-manager')->next('execute');
    $p->state('execute')->action(fn ($c) => $c->refundService->execute($c->get('order_id')));
    $p->state('done', type: StateType::End);
});
```

### ۴٫۳ آستانه‌های تأیید (قابل تنظیم)
```php
// config/approvals.php
return [
    'refund' => [
        'levels' => [
            ['max' => 5_000_000,   'approver' => 'shop-manager'],
            ['max' => 50_000_000,  'approver' => 'finance-manager'],
            ['max' => INF,         'approver' => 'super-admin'],
        ],
    ],
    'purchase' => [
        'levels' => [
            ['max' => 10_000_000,  'approver' => 'procurement-officer'],
            ['max' => 100_000_000, 'approver' => 'finance-manager'],
            ['max' => INF,         'approver' => 'ceo'],
        ],
    ],
    'discount' => [
        'levels' => [
            ['max_percent' => 10, 'approver' => 'shop-manager'],
            ['max_percent' => 30, 'approver' => 'finance-manager'],
            ['max_percent' => INF,'approver' => 'super-admin'],
        ],
    ],
];
```

---

## ۵. تفویض وظیفه (Duty Delegation)

```php
// مدیر وظیفه‌ای را موقتاً به دیگری واگذار می‌کند
Delegation::create([
    'from_user_id' => $manager->id,
    'to_user_id'   => $assistant->id,
    'duties'       => ['shop.order.refund', 'shop.discount.manage'],
    'starts_at'    => now(),
    'ends_at'      => now()->addDays(7),
    'reason'       => 'مرخصی',
    'requires_approval' => false,   // آیا اقدامات delegate نیازمند تأیید اصل است؟
]);
```
🔒 تفویض:
- در Audit ثبت می‌شود.
- پس از انقضا خودکار باطل می‌شود.
- نمی‌تواند فراتر از اختیارات خودِ فرد باشد.
- در `Gate::before` بررسی می‌شود (DOC-057 §6.1).

---

## ۶. ماتریس وظیفه ↔ نقش (RACI)

برای هر فرایند کلیدی، ماتریس RACI:
- **R**esponsible (اجراکننده)
- **A**ccountable (پاسخگو/تأییدکننده)
- **C**onsulted (مشورت‌شونده)
- **I**nformed (مطلع‌شونده)

### مثال: فرایند استرداد سفارش
| فعالیت | operator | manager | finance | auditor |
|---|:--:|:--:|:--:|:--:|
| درخواست استرداد | R | I | — | — |
| بررسی صلاحیت | R | A | — | — |
| تأیید مالی (< 5M) | — | A | C | I |
| تأیید مالی (≥ 5M) | — | C | A | I |
| اجرای استرداد | R | A | I | I |
| ثبت در دفتر کل | — | I | R | A |
| ممیزی | — | I | I | R |

---

## ۷. SLA و Escalation

```php
final class SlaPolicy
{
    /** @return array{due: \DateTimeImmutable, escalateTo: string} */
    public function for(string $dutyKey, ?User $assignee): array
    {
        $duty = $this->duties->get($dutyKey);
        $due = Clock::utcNow()->add(new \DateInterval($duty->sla));

        return [
            'due' => $due,
            'escalateTo' => $duty->escalation ?? $this->managerOf($assignee),
        ];
    }
}

// در schedule: بررسی وظایف overdue
Schedule::call(function (): void {
    foreach (Task::overdue()->get() as $task) {
        $this->escalate($task);
        Event::dispatch(new TaskEscalated($task->id));
    }
})->everyFifteenMinutes();
```

---

## ۸. وظایف خودکار (System Duties)

برخی وظایف توسط سیستم انجام می‌شوند (بدون انسان):
| وظیفه | زمان‌بندی | مسئول |
|---|---|---|
| پشتیبان‌گیری | روزانه ۰۳:۰۰ | system |
| پاکسازی session/cart منقضی | ساعتی | system |
| ارسال گزارش فروش روزانه | روزانه ۲۳:۰۰ | system → manager |
| بررسی SLA نقض‌شده | هر ۱۵ دقیقه | system |
| همگام‌سازی موجودی | هر ۵ دقیقه | system |
| پاکسازی لاگ قدیمی | هفتگی | system |

---

## ۹. ثبت و پیگیری وظایف

```sql
CREATE TABLE tvn_duties (
  id CHAR(26) PRIMARY KEY, key VARCHAR(100) UNIQUE,
  title_fa VARCHAR(190), required_permissions JSON,
  roles JSON, sla VARCHAR(20), escalation VARCHAR(100),
  requires_approval TINYINT(1), approval_chain VARCHAR(190),
  is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE tvn_task_assignments (
  id CHAR(26) PRIMARY KEY, duty_id CHAR(26), assignee_id BIGINT UNSIGNED,
  subject_type VARCHAR(100), subject_id VARCHAR(64),
  status VARCHAR(20) DEFAULT 'pending',   -- pending|in_progress|done|escalated|expired
  due_at DATETIME(3), completed_at DATETIME(3),
  created_at DATETIME(3)
);
```

---

## ۱۰. امنیت
| تهدید | کنترل |
|---|---|
| تفویض به فرد نامناسب | تأیید + Audit + انقضا |
| دورزدن تأیید | Workflow اجباری برای وظایف `requires_approval` |
| SLA نادیده‌گرفته | escalation خودکار |
| تعارض وظایف (SoD) | بررسی چهارچشمی |

---

## ۱۱. معیار پذیرش 🧪
- AC-DUTY-01: هر نقش وظایف مشخص با SLA دارد.
- AC-DUTY-02: گردش تصویب چندسطحی با آستانهٔ مبلغ کار می‌کند.
- AC-DUTY-03: تفویض موقت با انقضای خودکار.
- AC-DUTY-04: Escalation خودکار برای وظایف overdue.
- AC-DUTY-05: ماتریس RACI برای فرایندهای کلیدی مستند است.
