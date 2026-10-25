# گزارش پیاده‌سازی — M9 گام ۷: کیت ERP 🏭

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0097 (DOC-097) |
| نگارش | 0.14.0 → **0.15.0** |
| کامیت | `M9 Kit ERP` |
| ماژول | `modules/erp` (tavaan.erp) |
| کیت | `kits/erp` (tavaan.kit.erp) |
| تست | **۴۹ جدید** (۳۳ Unit + ۱۶ Feature) — سوئیت کامل **۱۵۸۳/۱۵۸۳ سبز** (۱۰٬۴۲۴ assertion) |
| گیت‌ها | arch:check ✓ (۱۵۱۲ فایل، ۰ نقض) • lint:php ✓ (۲۱۰۶ فایل) • security:scan ✓ (۰ high — ۸ med موجود) • module:validate ✓ (۶۳) • engine:validate ✓ (۵۲) |

## ۱. دامنهٔ تحویل

- **ماژول ERP** با ۶ بافتهٔ مرزی (accounting/inventory/procurement/sales/
  production/hr) — ۲۳ موجودیت، ۷ enum، ۱۰ رویداد، ۴ سرویس دامنهٔ خالص،
  ۶ پورت + ۶ آداپتر DB، ۹ سرویس Application، ۶ listener صدور سند،
  OutboxPublisher، PurchaseApprovalWorkflow، ErpAuditRecorder،
  ۹ کنترلر پنل، ۲۶ ویو RTL، ۲۴ جدول `erp_*`، ۲۸ مجوز، seed دمو.
- **کیت**: ۳ preset، تم صنعتی، ۱۰ نقش (۹ نقش مستند §6 + admin)، demo
  انتصاب تیم، راهنمای ادمین/کاربر.
- **E2E-01..08** مستند §10 کامل + AC-ERP-09/10/11/12.

## ۲. تصمیم‌های معماری

1. **یک ماژول با ۶ بافتهٔ داخلی** (به‌جای ۶ ماژول §1.1): مرزها با
   پورت Repository و رویداد حفظ شده؛ ارتباط بین بافته‌ها فقط از
   Outbox → هر بافته قابل استخراج به سرویس مستقل (AC-ERP-12). الگوی
   همسان با shop/lms/crm در M9. ماژول‌بندی ۶-گانه در بک‌لاگ 1.0 در صورت
   نیاز به استقرار جدا.
2. **Outbox درون‌ماژولی بدون ارجاع به کلاس موتور** (ARCH-003): ماژول
   مستقیماً در `tvn_outbox` (schema موتور event) و **درون تراکنش
   کسب‌وکار** می‌نویسد؛ رلهٔ درون‌خطی پس از commit (claim اتمیک با
   owner-token + attempts→failed پس از ۵ بار — همسان با `DatabaseOutbox`).
   در preset سازمانی `inline_relay=false` و کرون `event:outbox-relay`.
3. **Audit با موتور رسمی**: `AuditEvent` (VO readonly) با allow صریح و
   مستند در `config/architecture.php` (الگوی SDK مانند `Console\Command`).
   نام رویدادهای مالی ERP در `audit.events` allowlist موتور در provider
   ادغام می‌شود (بدون تغییر config سراسری در ریپو). fail-open: خطای
   audit جریان مالی را نمی‌شکند.
4. **شناسایی بدهی در رسید** (نه فاکتور تأمین‌کننده): سند خرید در رسید
   کالا صادر می‌شود (دارایی + بدهی هم‌زمان)؛ فاکتور مدرک تسویه است.
   ماندهٔ تأمین‌کننده = Σرسید − Σپرداخت.
5. **بهایابی weighted_avg** (پیش‌فرض مستند): رسید میانگین را به‌روز
   می‌کند؛ حواله/COGS/مصرف تولید با avg جاری. FIFO/LIFO بک‌لاگ.
6. **Workflow = لایهٔ تاریخچه/visualization**؛ ماشین‌حالت ماژول مرجع
   صحت است (fire با log-warning — الگوی LMS). مسیر تأیید را
   ApprovalRouter (دامنهٔ خالص، تست‌پذیر) تصمیم می‌گیرد.

## ۳. انحراف‌های مستند (همه در DOC-097 §13 ثبت شد)

| مستند | پیاده‌سازی | دلیل |
|---|---|---|
| DECIMAL(18,4) پول | int minor-unit (bigint) | ADR-0013 — سازگاری با همهٔ کیت‌ها؛ قانون طلایی با === int قطعی است |
| ۶+ ماژول مستقل | ۱ ماژول/۶ بافته | §۲ تصمیم ۱ |
| BIGINT PK اسناد | ULID char(26) | استاندارد فریمورک (accounts/periods/attendance همچنان bigint — شمارشی) |
| بودجه/دارایی ثابت (§1.1) | بک‌لاگ 1.0 | خارج از E2E-01..08؛ کنترل دامنه |
| Multi-Tenancy (§8) | سطح استقرار (موتور tenancy) | ماژول v1 تک‌شرکتی per نصب |
| لیله مرخصی در حقوق | ثبت leave_days — کسر از deductions | قوانین مرخصی سازمانی است |

## ۴. باگ‌های یافت‌شده حین پیاده‌سازی

1. `PurchaseOrder/SalesOrder::create` اقلام را با شناسهٔ تولیدشدهٔ سرویس
   می‌ساخت نه شناسهٔ واقعی سفارش → آیتم‌ها یتیم؛ رفع با `withParent()`
   در create موجودیت.
2. `vouchers()` بدون خطوط hydrate می‌شد → `totalDebit()=0` در فهرست‌ها؛
   رفع با بارگذاری خطوط per سند.
3. `erp_payroll_runs.slip_count` از قلم افتاده بود (QueryException) → به
   migration افزوده شد.
4. `FiscalPeriod` پس از insert بدون id (bigint autoincrement) →
   `assignId()` در savePeriod.
5. allowlist `audit.events` موتور رویدادهای ERP را بی‌صدا drop می‌کرد →
   ادغام idempotent در provider.
6. تداخل نام `Voucher::date()` (accessor) با helper استاتیک →
   `parseDate()`.

### ۴٫۱ دو باگ موتور (بدنهٔ فریمورک — کشف با smoke واقعی ERP)

7. 🔴 **`SessionGuard` هرگز نشست HTTP واقعی را نمی‌دید** (auth — latent از
   M6): `StartSessionMiddleware` نشست را به clone درخواست
   (`withAttribute`) می‌بندد و clone در pipeline جلو می‌رود، اما
   `RequestStack` درخواست اصلیِ بدون attribute را نگه می‌دارد →
   `SessionGuard::session()` (که از `requestStack->current()` می‌خواند)
   همیشه null → **هر صفحهٔ auth-guard در HTTP واقعی به /login ریدایرکت
   می‌شد**. چرا دیده نشده بود؟ تست‌های Feature با `actingAs()` کاربر را
   مستقیم در حافظه `setUser` می‌کنند (بدون نشست) و smoke کیت‌های پیشین فقط
   صفحات عمومی و «302 (auth) ✓» را بررسی کرده بودند (گزارش M9 گام ۱، خط
   smoke blog). **فیکس**: fallback در `AuthManager::sessionResolver` به
   instance نشستِ scope-شدهٔ container (`'session'` — که
   StartSessionMiddleware هر درخواست می‌بندد و در terminate فلاش می‌شود) +
   تست رگرسیون `testSessionCookieAuthenticatesThroughRealKernel` (کوکی
   نشست واقعی → kernel کامل → ۲۰۰).
8. **`Request::create` هدر `HTTP_COOKIE` را پارس نمی‌کرد** (http): فقط
   پارامتر صریح `$cookies` — کوکی نشست در مسیر `MakesHttpRequests::call`
   گم می‌شد؛ اکنون هم‌راستا با `fromGlobals` وقتی `$cookies` خالی است از
   هدر پارس می‌شود.

هر دو فیکس با ۱۱۴ تست engines/{auth,http,session} و سوئیت کامل (۱۵۸۳)
رگرسیون‌گیری شد.

## ۵. راستی‌آزمایی عملیاتی (پس از نصب واقعی)

- `tvn rbac:sync` (۲۸ مجوز erp.*) + `tvn kit:install erp --with-demo`
  روی DB مشترک (هم‌زیستی با blog/shop/lms/crm): ۳۳ حساب، ۲ انبار، ۶ کالا،
  ۲ تأمین‌کننده، ۲ مشتری، ۱ BOM، ۳ پرسنل + کارکرد، ۹ نقش، ۱۵ انتصاب ✓
- **HTTP smoke واقعی: ۳۵/۳۵ check سبز** (`php -S` + نشست کوکی‌دار واقعی):
  - ۱۶ صفحهٔ پنل/گزارش/CSV → ۲۰۰ با admin
  - جریان خرید E2E زنده: POST PO (۳×۹۰۰٬۰۰۰) → submit → auto-approve
    (زیر آستانه) → نمونهٔ workflow در `tvn_process_instances` → رسید →
    موجودی ۳ + **سند خودکار posted تراز ۲٬۷۰۰٬۰۰۰** → outbox pending=0 →
    فاکتور `SINV-20261025-9D78AC` → پرداخت → PO=paid
  - حقوق زنده: payroll ماه → gross ۴۱۲٬۳۸۶٬۳۶۴ ریال (۳ پرسنل +
    اضافه‌کاری) → سند هزینه posted
  - audit: ۱۳ ردیف زنجیره + صفحهٔ audit «سالم» ✓
  -ماتریس نقش: حسابرس → reports 200 / inventory **403** ✓
- نکتهٔ مهم: این smoke **نخستین احراز هویت نشست‌محور واقعی روی HTTP** در
  تاریخ فریمورک بود — که باگ نهفتهٔ §۴٫۱-۷ را آشکار کرد (بدون آن، پنل
  همهٔ کیت‌ها در استقرار واقعی پشت /login قفل بود).

## ۶. بدهی فنی (ثبت برای 1.0)

- Budgeting/دارایی ثابت/استهلاک (DOC-097 §1.1)
- FIFO/LIFO + lot/serial tracking انبار
- مغایرت‌گیری بانک/مشتری (§4 گام ۲)
- تأیید چند-ارزی (currency conversion)
- cost center/project روی ردیف سند (ستون‌ها موجود — UI/گزارش ندارند)
- اعلان‌های po_needs_approval/low_stock (config آماده — Notifier در بک‌لاگ)
