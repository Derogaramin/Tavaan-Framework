# راهنمای مدیر — کیت BPMS 🔄

## شروع سریع

1. نصب: `php bin/tvn kit:install bpms --with-demo`
2. ورود با `admin@blog.test` / `password` → `/admin/bpms`
3. داشبورد پایش: نمونه‌های فعال/تکمیل‌شده، میانگین چرخه، گلوگاه‌ها،
   انطباق SLA، بار کاری assigneeها و فعالیت اخیر (+ خروجی CSV).
4. دادهٔ دمو: فرایند «درخواست مرخصی» (سناریوی مستند DOC-098 §3) و
   «تأیید خرید موازی» (parallel gateway) با سه نمونهٔ در جریان/تکمیل‌شده.

## طراحی فرایند (فرایندساز)

1. `/admin/bpms/definitions/create` (مجوز `bpms.definition.manage`):
   - **کلید** (لاتین، یکتا per نسخه) و **نام** فارسی
   - **تعریف JSON** — گراف `elements` (§3): `startEvent` / `userTask`
     (assignee/form/due) / `serviceTask` (action) / `exclusiveGateway`
     (conditions با expr) / `parallelGateway` (fork/join) / `endEvent`
   - **فرم‌ها JSON** — `form_schema` (§4): فیلدها با type/label/required/
     min/max/options؛ قواعد validation خودکار تولید می‌شود
2. «ذخیره» → پیش‌نویس. «انتشار» → validate کامل (AC-BPMS-11) + فعال‌سازی
   نسخه + ثبت mirror در موتور workflow.
3. نسخه‌بندی: هر انتشار نسخهٔ جدید می‌شود؛ نمونه‌های جاری روی نسخهٔ خود
   می‌مانند. «فعال‌سازی» نسخهٔ قدیمی = بازگشت کنترل‌شده.

### قواعد شرط (expr) — امن بدون eval 🔒

فقط: `variables.x > 5`، `==`/`!=`/`===`، `<`/`<=`/`>`/`>=`، `&&`/`||`/`!`،
`+ - * / %`، پرانتز، رشته/عدد/true/false/null و مسیر dotted.
فراخوانی تابع، `$var`، `;`، backtick و کاراکترهای تزریق → **رد در طراحی و
اجرا** (AC-BPMS-12).

### Service task actionها

اکشن‌ها **از پیش ثبت می‌شوند** (نه از ورودی کاربر):

```php
// در provider اپلیکیشن شما
$app->make(\Modules\Bpms\Application\UseCases\ActionRegistry::class)
    ->register('approveLeave', function (\Modules\Bpms\Application\UseCases\ProcessContext $ctx): void {
        $leaveId = (int) $ctx->get('leave_id');
        // … اتصال به ماژول دامنهٔ شما
        $ctx->set('leave_approved', true);
    });
```

داخلی‌های موجود: `noop`، `log`، `mark_done`، `notify_initiator`.

## تایمر و escalation

- روی userTask: `"due": "P2D"` → سررسید روی کار ذخیره می‌شود.
- تایمر تعریف: `{"on":"manager_approval","after":"P2D","action":"escalate"}`.
- `php bin/tvn bpms:check-timers` (schedule هر دقیقه): کارهای
  سررسیدگذشته → `escalate` (reassign به نقش `bpms.timers.escalate_role`)
  یا اکشن ثبت‌شدهٔ دلخواه + رویداد `bpms.task.escalated` + اعلان.
  idempotent — اجرای مکرر بی‌اثر (AC-BPMS-13).

## مدیریت نمونه‌ها

- `/admin/bpms/instances` — فهرست با فیلتر وضعیت/فرایند/کلید کسب‌وکار.
- صفحهٔ نمونه: نمودار با **هایلایت توکن فعال**، متغیرها (کلیدهای حساس
  mask)، کارها، timeline کامل تاریخچه با بازیگر/زمان‌سنجی.
- عملیات مدیریتی (`bpms.instance.manage|terminate`): تعلیق/ادامه/پایان
  اجباری (لغو کارهای باز) — همه با ثبت در **hash chain موتور audit**
  و راستی‌آزمایی با `php bin/tvn audit:verify`.

## نقش‌ها (DOC-098 §7)

| نقش | دسترسی |
|---|---|
| `process-designer` | طراحی/ویرایش/انتشار تعاریف و فرم‌ها |
| `process-admin` | مدیریت نمونه‌ها، terminate، انتصاب اجباری کار، همه‌چیز |
| `task-assignee` | inbox، claim/complete/delegate کارهای محوله |
| `monitor` | داشبورد پایش + خواندن نمونه‌ها/کارها |
| `auditor` | ردپای audit + تاریخچه + پایش |
| `manager` | نقش کسب‌وکاری تأییدکننده (سناریوهای دمو) |

## Realtime

کانال‌های SSE: `bpms` (سراسری) و `bpms.instance.{ULID}` — مشترکین از راه
`GET /events/stream?channels=bpms` کارهای جدید/تکمیل/escalation را
بی‌درنگ می‌گیرند (preset standard/enterprise).

## Presetها

| Preset | ویژگی‌ها |
|---|---|
| `minimal` | بدون mirror/realtime — اعلان‌های ضروری |
| `standard` | پیش‌فرض DOC-098: mirror + realtime + اعلان کامل + تایمر هر دقیقه |
| `enterprise` | سقف نمونه کمتر، escalate تهاجمی، mask گسترده، batch تایمر بزرگ |

## عیب‌یابی

- تعریف start نمی‌شود → نسخهٔ فعال published وجود دارد؟ (`/admin/bpms/definitions`)
- `unknown_action` → اکشن serviceTask در ActionRegistry ثبت نشده (کد اپلیکیشن).
- کار در inbox نقش دیده نمی‌شود → کلید نقش در RBAC با `role:<key>` تعریف
  یکسان است؟ (`php bin/tvn rbac:sync`)
- timer کار نمی‌کند → schedule/cron فعال است؟ دستی: `php bin/tvn bpms:check-timers`
