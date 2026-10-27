# موتور گردش‌کار — `Workflow`

> M7 — State Machine/BPM خودنوشته: DSL تعریف فرایند (ProcessBuilder) + JSON/Schema (Designer)، Guard/Action، دروازهٔ انحصاری (اولین guard موفق) و موازی (fork/join با Token)، Human Task (assignee role:/user: + claim/complete/delegate)، Timer (انسانی/ISO-8601 + workflow:timers)، Sub-Process (پیش‌برد والد با تکمیل فرزند)، History، Visualizer (DOT/Mermaid)، fire اتمیک (snapshot/restore)، محافظ DoS (max_active_instances) و mask متغیرهای حساس.
>
> مستند کامل: [WORKFLOW](../../docs/03-engines/workflow.md) • تیکت: TVN-0071 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.workflow` |
| namespace | `Tavaan\Engine\Workflow\` |
| Facade | `Tavaan\Core\Facade\Facades\Workflow` (accessor: `workflow`) |
| پیکربندی | `config/workflow.php` → `config('workflow.*')` |
| Provider | `Tavaan\Engine\Workflow\WorkflowServiceProvider` |
| وابستگی سخت | `config` |
| وابستگی نرم | `event`، `database`، `migration`، `rbac`، `queue`، `console`، `auth` |
| فرمان‌های CLI | `tvn workflow:timers`، `tvn workflow:list` |
| آمار | 31 فایل / ~3,807 خط در src • 1 فایل تست (37 متد) |

## شروع سریع

```bash
# تست‌های موتور (37 متد)
php bin/tvn test engines/workflow

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Workflow;

// API عمومی و مثال‌ها: docs/03-engines/workflow.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/workflow/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/workflow.md`](../../docs/03-engines/workflow.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
