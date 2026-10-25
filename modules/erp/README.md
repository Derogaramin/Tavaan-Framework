# ماژول ERP — `tavaan.erp` 🏭

> برنامه‌ریزی منابع سازمانی یکپارچه با **دفتر کل مرکزی** — DOC-097 (TVN-0097).
> نصب از راه کیت: `php bin/tvn kit:install erp --with-demo`

## دامنه

شش بافتهٔ مرزی (Bounded Context) در یک ماژول — ارتباط بین بافته‌ها **فقط**
با رویدادهای Outbox (ADR-0009) و سرویس‌های Application؛ هر بافته آمادهٔ
استخراج به سرویس مستقل است (AC-ERP-12):

| بافته | جریان اصلی |
|---|---|
| **Accounting** | chart of accounts → سند دوطرفه (draft→approved→posted/voided) → دورهٔ مالی و بستن حساب |
| **Inventory** | انبار/کالا → رسید/حواله/انتقال/اصلاح (append-only) → بهای میانگین موزون |
| **Procurement** | PO → تأیید سلسله‌مراتبی (workflow بر اساس مبلغ) → رسید → فاکتور → پرداخت |
| **Sales** | سفارش → حواله (COGS) → فاکتور (درآمد) → دریافت |
| **Production** | BOM → work order → مصرف مواد (WIP) → محصول (بهای تمام‌شده) |
| **HR** | پرسنل → کارکرد ماهانه → PayrollRun → فیش → سند هزینهٔ حقوق |

## اصل یکپارچگی: دفتر کل مرکزی (DOC-097 §1.2)

هر رویداد عملیاتی در **همان تراکنش کسب‌وکار** در `tvn_outbox` ثبت می‌شود؛
پس از commit با رلهٔ درون‌خطی (یا `event:outbox-relay` زمان‌بندی‌شده) منتشر و
توسط listenerهای حسابداری به سند دوطرفه تبدیل می‌شود:

| رویداد | سند خودکار |
|---|---|
| `inventory.stock.received` (خرید) | بدهکار موجودی (per نوع کالا) / بستانکار پرداختنی تجاری |
| `inventory.stock.issued` (فروش) | بدهکار بهای کالای فروش‌رفته / بستانکار موجودی |
| `inventory.stock.issued` (مصرف تولید) | بدهکار کالای در جریان ساخت / بستانکار مواد اولیه |
| `sales.invoice.issued` | بدهکار دریافتنی تجاری / بستانکار درآمد فروش |
| `sales.payment.received` | بدهکار بانک/صندوق / بستانکار دریافتنی |
| `production.order.completed` | بدهکار محصول ساخته‌شده / بستانکار WIP |
| `hr.payroll.processed` | بدهکار هزینهٔ حقوق / بستانکار حقوق پرداختنی |

🔒 **قانون طلایی (AC-ERP-09):** هر سند پیش از ذخیره/ثبت `SUM(debit) === SUM(credit)`
(int minor-unit — ADR-0013). سند `posted` غیرقابل تغییر است — اصلاح فقط با
سند معکوس (`reverse_of`).

## تأیید سلسله‌مراتبی خرید (DOC-097 §3)

`ApprovalRouter` (دامنهٔ خالص) + workflow `erp-purchase-approval`:

```
مبلغ ≤ finance_threshold (۱۰۰M ریال)  → تأیید خودکار
finance < مبلغ ≤ ceo_threshold (۵۰۰M) → مدیر مالی (erp.purchase.approve-finance)
مبلغ > ceo_threshold                   → مدیر مالی → مدیرعامل (erp.purchase.approve-ceo)
```

آستانه‌ها در `config/erp.php` قابل تنظیم سازمانی هستند.

## دورهٔ مالی و بستن حساب (DOC-097 §4)

`PeriodCloseService` سند بستن تراز را صادر و posted می‌کند:
درآمد/هزینه → خلاصهٔ سود و زیان (3201) → سود انباشته (3301)؛ سپس دوره
`closed` و **سند جدید در بازهٔ بسته رد می‌شود** (قفل دوره).

## Audit (DOC-097 §7 / AC-ERP-10)

رویدادهای مالی (سند/تأیید/ثبت/ابطال/رسید/حقوق/بستن دوره) با
`ErpAuditRecorder` در hash chain موتور **audit** ثبت می‌شوند —
راستی‌آزمایی: `php bin/tvn audit:verify`.
`Tavaan\Engine\Audit\AuditEvent` (VO readonly) با allow صریح و مستند در
`config/architecture.php` مجاز است (الگوی SDK مانند `Console\Command`).

## گزارش‌ها (DOC-097 §5)

تراز آزمایشی • صورت سود و زیان • ترازنامه (A = L + E + NI) • گردش کالا •
بهای تمام‌شدهٔ تولید • حساب مشتری/تأمین‌کننده • حقوق و دستمزد — همگی از
اسناد **posted** + خروجی CSV.

## انبار و بهایابی

- حرکات موجودی **append-only** (`erp_stock_movements`) — رسید/حواله/انتقال/اصلاح
- بهایابی: **میانگین موزون** (`weighted_avg`) — رسید جدید میانگین را به‌روز
  می‌کند؛ حواله با avg جاری (FIFO/LIFO در بک‌لاگ 1.0)
- کمبود موجودی = خطا (کنترل available پیش از حواله)
- نقطهٔ سفارش → فهرست «زیر نقطهٔ سفارش» در داشبورد

## ساختار

```
src/
├── Domain/{Entities,Enums,Events,Repositories,Services}
├── Application/UseCases/      (Accounting, Inventory, Procurement, Sales,
│                               Production, Hr, PeriodClose, ErpReport, AuditRecorder)
├── Infrastructure/
│   ├── Persistence/           (۶ آداپتر DB — هر بافته یک پورت)
│   ├── Integration/           (OutboxPublisher — ADR-0009)
│   ├── Listeners/             (۶ listener صدور سند)
│   └── Workflow/              (PurchaseApprovalWorkflow)
└── Http/Controllers/Admin/    (۹ کنترلر پنل)
```

جداول: ۲۴ جدول `erp_*` — مهاجرت: `migrations/2026_10_25_700000_create_erp_tables.php`.

## مسیرهای پنل

همه زیر `/admin/erp` با `AuthenticateMiddleware` + بررسی مجوز در کنترلرها:
داشبورد • اسناد (+ایجاد/تأیید/ثبت/ابطال) • حساب‌ها • دوره‌ها • انبار
(رسید/حواله/انتقال/اصلاح + گردش) • خرید (PO + تأییدها + رسید + فاکتور + پرداخت)
• فروش (سفارش + تحویل + فاکتور + دریافت) • تولید (BOM + work order)
• حقوق (پرسنل/کارکرد/payroll) • گزارش‌ها (+CSV) • ردپای audit.

## مجوزها (RBAC)

۲۸ مجوز `erp.*` — نقش‌های مستند §6 (accountant, finance-manager,
warehouse-keeper, procurement-officer, sales-officer, hr-officer,
production-planner, auditor, erp-admin) در `kits/erp/seed/roles.json`.

## نکات پیاده‌سازی (انحراف‌های مستند)

1. **DECIMAL(18,4) → int minor-unit** (ADR-0013؛ مانند shop/lms/crm).
2. **مقادیر (quantity):** `decimal(18,3)` — float با گرد‌کردن ۳ رقم (پول همچنان int).
3. **یک ماژول با ۶ بافتهٔ داخلی** به‌جای ۶ ماژول جدا — مرزهای بافته با
   پورت/رویداد حفظ شده و تجزیه‌پذیری (AC-ERP-12) با Outbox تضمین می‌شود؛
   الگوی همسان با shop/lms/crm در M9.
4. **Budgeting/Asset** (§1.1) و **Multi-Tenancy** (§8 — موتور tenancy در سطح
   استقرار آماده است) در بک‌لاگ 1.0؛ E2E-01..08 مستند §10 کامل پوشش داده شده.
5. فاکتور تأمین‌کننده سند جدا صادر نمی‌کند — بدهی در **رسید** شناسایی می‌شود
   (رسید = رویداد شناسایی دارایی/بدهی؛ فاکتور = مدرک تسویه).

## تست

- Unit: قانون طلایی سند + immutability + معکوس • چرخه‌های وضعیت • میانگین
  موزون • ApprovalRouter • PayrollCalculator • BOM explode • TrialBalance/بستن دوره
- Feature: E2E-01..08 مستند §10 (خرید/فروش/تولید/حقوق/بستن دوره/تأیید
  سلسله‌مراتبی/audit/تراز همهٔ اسناد)

```bash
php bin/tvn test --filter=Erp
```
