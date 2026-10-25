# DOC-097 — کاربرد ERP (برنامه‌ریزی منابع سازمانی) 🏭

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-097 |
| سطح | سازمانی (بالاترین پیچیدگی) |
| معماری | `hexagonal` + آماده برای microservice |
| ماژول‌ها | core-user, accounting, inventory, procurement, sales, hr, production, asset, report, workflow, audit |
| موتورها | همه (به‌ویژه workflow, report, audit, rbac, tenancy) |

---

## ۱. دامنه
یکپارچه‌سازی فرایندهای سازمانی: مالی/حسابداری، موجودی/انبار، تدارکات/خرید،
فروش، منابع انسانی، تولید، دارایی ثابت و گزارش‌گیری.

### ۱٫۱ Bounded Contextها (هرکدام یک ماژول)
```
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  Accounting  │ │  Inventory   │ │  Procurement │ │    Sales     │
│ (دفتر کل/معین)│ │ (انبار/رسید) │ │ (خرید/تأمین) │ │ (فروش/سفارش) │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│      HR      │ │  Production  │ │    Asset     │ │   Budgeting  │
│ (پرسنل/حقوق) │ │ (تولید/BOM)  │ │ (دارایی ثابت)│ │   (بودجه)    │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
```

### ۱٫۲ اصل یکپارچگی: **دفتر کل مرکزی**
همهٔ ماژول‌های عملیاتی (خرید/فروش/انبار/حقوق) اسناد مالی تولید می‌کنند که
به **دفتر کل (General Ledger)** در ماژول accounting می‌ریزند.

```
فروش → فاکتور → سند درآمد (accounting)
خرید → رسید → سند هزینه/دارایی (accounting)
انبار → حواله → سند موجودی (accounting)
حقوق → فیش → سند هزینهٔ حقوق (accounting)
```

## ۲. ماژول‌های اصلی

### ۲٫۱ Accounting (حسابداری)
```sql
CREATE TABLE erp_accounts (              -- سرفصل حساب‌ها (chart of accounts)
  id BIGINT PK, code VARCHAR(50) UNIQUE, title VARCHAR(190),
  parent_id BIGINT, type VARCHAR(20),     -- asset|liability|equity|revenue|expense
  level INT, is_leaf TINYINT(1),
  normal_balance VARCHAR(10)              -- debit|credit
);

CREATE TABLE erp_vouchers (              -- سند حسابداری
  id CHAR(26) PRIMARY KEY, voucher_number VARCHAR(50),
  date DATE, description TEXT, 
  status VARCHAR(20) DEFAULT 'draft',     -- draft|approved|posted|voided
  source_module VARCHAR(50), source_id CHAR(26),  -- ماژول مبدأ (فروش/خرید)
  total_debit DECIMAL(18,4), total_credit DECIMAL(18,4),
  created_by BIGINT, approved_by BIGINT, posted_at DATETIME(3),
  KEY idx_date (date), KEY idx_source (source_module, source_id)
);

CREATE TABLE erp_voucher_lines (         -- ردیف سند
  id CHAR(26) PRIMARY KEY, voucher_id CHAR(26),
  account_id BIGINT NOT NULL, debit DECIMAL(18,4) DEFAULT 0,
  credit DECIMAL(18,4) DEFAULT 0, description VARCHAR(255),
  cost_center_id BIGINT NULL, project_id CHAR(26) NULL,
  KEY idx_voucher (voucher_id), KEY idx_account (account_id)
);
```
🔒 **قانون طلایی حسابداری:** در هر سند، `SUM(debit) === SUM(credit)` (تراز).
🔒 سند `posted` غیرقابل تغییر (immutable) — اصلاح با سند معکوس.

### ۲٫۲ Inventory (انبار)
```sql
CREATE TABLE erp_warehouses (id CHAR(26) PK, name, code, address);
CREATE TABLE erp_inventory_items (
  id CHAR(26) PK, sku VARCHAR(64) UNIQUE, name VARCHAR(255),
  unit VARCHAR(20),                       -- عدد/کیلوگرم/متر
  reorder_point INT, reorder_qty INT,
  valuation_method VARCHAR(20)            -- fifo|weighted_avg|lifo
);
CREATE TABLE erp_stock (
  warehouse_id CHAR(26), item_id CHAR(26),
  quantity DECIMAL(18,4), reserved DECIMAL(18,4), available DECIMAL(18,4),
  PRIMARY KEY(warehouse_id, item_id)
);
CREATE TABLE erp_stock_movements (       -- رسید/حواله (append-only)
  id CHAR(26) PK, warehouse_id CHAR(26), item_id CHAR(26),
  type VARCHAR(20),                       -- receipt|issue|transfer|adjustment
  quantity DECIMAL(18,4), unit_cost DECIMAL(18,4),
  reference_type VARCHAR(50), reference_id CHAR(26),  -- خرید/فروش/تولید
  created_at DATETIME(3)
);
```

### ۲٫۳ Procurement (خرید)
```
درخواست خرید → استعلام → سفارش خرید (PO) → رسید انبار → فاکتور → پرداخت
```
هر گام با Workflow (تأیید سلسله‌مراتبی بر اساس مبلغ).

### ۲٫۴ Production (تولید)
```sql
CREATE TABLE erp_bom (                   -- Bill of Materials
  id CHAR(26) PK, product_id CHAR(26), version INT,
  components JSON                        -- [{item_id, qty}, ...]
);
CREATE TABLE erp_work_orders (
  id CHAR(26) PK, product_id CHAR(26), planned_qty DECIMAL,
  produced_qty DECIMAL, status VARCHAR(20), started_at, completed_at
);
```
→ تولید: مصرف مواد (حواله) + تولید محصول (رسید) + سند بهای تمام‌شده.

### ۲٫۵ HR (منابع انسانی)
```
پرسنل │ قرارداد │ کارکرد │ حقوق و دستمزد │ مرخصی │ ارزیابی عملکرد
```
→ فیش حقوق → سند حسابداری (هزینهٔ حقوق).

## ۳. گردش‌کارهای سازمانی (Workflow)

ERP پر از فرایندهای تأیید سلسله‌مراتبی است:
```
خرید > ۱۰۰M ریال → مدیر مالی → مدیرعامل
مرخصی > ۳ روز → سرپرست → مدیر HR
فروش اعتباری → اعتبارسنجی → مدیر فروش
```
→ با موتور `workflow` (DOC-071) و Guard بر اساس مبلغ/نقش.

## ۴. دورهٔ مالی و بستن حساب
```
1. ثبت اسناد در طول دوره
2. مغایرت‌گیری (بانک، مشتریان، تامین‌کنندگان)
3. اصلاحات (استهلاک، پیش‌پرداخت)
4. بستن حساب‌های موقت (درآمد/هزینه) → سود و زیان
5. ترازنامه
```

## ۵. گزارش‌ها (report engine)
| گزارش | ماژول |
|---|---|
| تراز آزمایشی | accounting |
| صورت سود و زیان | accounting |
| ترازنامه | accounting |
| گردش کالا | inventory |
| بهای تمام‌شده | production |
| حساب مشتری/تامین‌کننده | sales/procurement |
| بودجه در برابر عملکرد | budgeting |
| حقوق و دستمزد | hr |

## ۶. نقش‌ها (نمونه)
| نقش | حوزه |
|---|---|
| `accountant` | ثبت/تأیید سند |
| `finance-manager` | بستن دوره، گزارش مالی |
| `warehouse-keeper` | رسید/حواله انبار |
| `procurement-officer` | خرید |
| `sales-officer` | فروش |
| `hr-officer` | پرسنل/حقوق |
| `production-planner` | تولید |
| `auditor` | فقط خواندن + audit log |
| `erp-admin` | همه |

## ۷. Audit و انکارناپذیری 🔒
ERP نیازمند Audit کامل است (موتور `audit`):
- هر سند مالی: ایجاد/تأیید/ثبت با hash chain
- تغییرات با رد کاربر/زمان/IP
- غیرقابل حذف (soft void با رد)

## ۸. Multi-Tenancy
ERP سازمانی اغلب چند‌شرکتی است → موتور `tenancy` با استراتژی B (DB-per-Tenant)
یا Shared با `company_id`.

## ۹. رویدادها (یکپارچگی بین ماژول)
| رویداد | تولید | مصرف |
|---|---|---|
| `inventory.stock.received` | inventory | accounting (سند), procurement |
| `sales.invoice.issued` | sales | accounting (درآمد), inventory (کاهش) |
| `hr.payroll.processed` | hr | accounting (هزینهٔ حقوق) |
| `production.order.completed` | production | inventory, accounting (بها) |
| `procurement.po.approved` | procurement | budgeting (تعهد) |

🔒 این رویدادها از **Outbox** می‌روند (سازگاری DB+Event) تا دفتر کل همیشه درست بماند.

## ۱۰. تست E2E (چرخهٔ کامل)
| ID | سناریو |
|---|---|
| E2E-01 | خرید: PO → رسید → سند → پرداخت |
| E2E-02 | فروش: سفارش → حواله → فاکتور → سند درآمد |
| E2E-03 | تولید: BOM → work order → مصرف → محصول → بها |
| E2E-04 | حقوق: کارکرد → فیش → سند |
| E2E-05 | بستن دوره → ترازنامه متوازن |
| E2E-06 | Workflow تأیید سلسله‌مراتبی خرید |
| E2E-07 | Audit trail کامل |
| E2E-08 | تراز سند (debit=credit) در همه |

## ۱۱. معیار پذیرش 🧪
- AC-ERP-01..08.
- AC-ERP-09: همهٔ اسناد مالی تراز هستند (debit=credit).
- AC-ERP-10: Audit chain با `audit:verify` سالم.
- AC-ERP-11: یکپارچگی بین‌ماژولی با Outbox (بدون نشت).
- AC-ERP-12: قابل تجزیه به سرویس‌های مستقل (آمادگی microservice).

## ۱۲. مراجع
| منبع | الهام |
|---|---|
| Odoo | ماژول‌بندی، یکپارچگی | معماری ماژولار تَوان |
| ERPNext (Frappe) | DocType، workflow | — |
| SAP | فرایندهای مالی، دوره | مفاهیم حسابداری |
| استاندارد حسابداری ایران | chart of accounts، صورت‌ها | — |

---

## ۱۳. وضعیت پیاده‌سازی (M9 — TVN-0097) ✅

> **تکمیل‌شده در نگارش 0.15.0** — ماژول `tavaan.erp` + کیت `tavaan.kit.erp`
> (نصب: `php bin/tvn kit:install erp --with-demo`). جزئیات:
> `docs/reports/M9-erp-implementation-report.md`.

| بخش | وضعیت |
|---|---|
| معماری | **یک ماژول hexagonal با ۶ بافتهٔ مرزی** (accounting/inventory/procurement/sales/production/hr) — هر بافته پورت Repository و سرویس Application مستقل؛ ارتباط بین بافته‌ها **فقط رویداد Outbox** (آمادهٔ تجزیه به microservice — AC-ERP-12) |
| دفتر کل (§1.2/§2.1) | chart of accounts درختی ۵-نوعی با normal_balance • سند دوطرفه با **قانون طلایی** `SUM(debit)===SUM(credit)` int minor-unit (AC-ERP-09) • draft→approved→**posted=immutable**→voided **فقط با سند معکوس** • دورهٔ مالی + **قفل سند در دورهٔ بسته** |
| بستن حساب (§4) | `PeriodCloseService`: درآمد/هزینه → خلاصهٔ سود و زیان (۳۲۰۱) → سود انباشته (۳۳۰۱) در یک سند بستنِ تراز/posted + رویداد `accounting.period.closed` |
| انبار (§2.2) | movements **append-only** • رسید/حواله/انتقال/اصلاح • بهایابی **میانگین موزون** • کنترل کمبود • reorder point |
| خرید (§2.3/§3) | PO → **تأیید سلسله‌مراتبی مبلغ‌محور** (ApprovalRouter + workflow `erp-purchase-approval` با permissionهای مالی/CEO) → رسید (بها از PO + گارد over-receipt) → فاکتور → پرداخت؛ آستانه‌ها ۱۰۰M/۵۰۰M ریال config |
| فروش/تولید/حقوق | سفارش→حواله(COGS snapshot)→فاکتور(درآمد)→دریافت • BOM نسخه‌دار→work order→مصرف(WIP)→محصول(بهای تمام‌شده) • کارکرد→فیش(overtime 1.4×220h + مالیات آستانه‌دار)→سند→پرداخت |
| یکپارچگی/Outbox (§9 🔒) | رویداد **درون تراکنش کسب‌وکار** در `tvn_outbox` (claim اتمیک + attempts→failed) + رلهٔ درون‌خطی پس از commit (سازمانی: `event:outbox-relay` cron) — ۶ listener سند خودکار؛ **بدون نشت** (AC-ERP-11) |
| Audit (§7) | ۲۲ رویداد مالی در **hash chain موتور audit** (`Tavaan\Engine\Audit\AuditEvent` با allow صریح SDK در config/architecture.php) • fail-open • `tvn audit:verify` + صفحهٔ audit پنل (AC-ERP-10 با tamper-detection تست‌شده) |
| گزارش‌ها (§5) | تراز آزمایشی / سود و زیان / ترازنامه (A=L+E+NI با flag) / گردش حساب / گردش کالا / بهای تمام‌شده / حساب اشخاص / حقوق + ۵ خروجی CSV — همه از اسناد posted |
| نقش‌ها (§6) | هر ۹ نقش مستند + admin — ۲۸ مجوز `erp.*` (`kits/erp/seed/roles.json`) |
| Multi-Tenancy (§8) | در سطح استقرار با موتور tenancy (استراتژی DB-per-Tenant/Shared) — ماژول v1 تک‌شرکتی per نصب |
| تست | `modules/erp/tests/` — **۴۹ تست** (۳۳ Unit + ۱۶ Feature) |

انطباق: E2E-01 (PO→تأیید خودکار→رسید→سند موجودی/پرداختنی→فاکتور→پرداخت→سند
بانک + صفرشدن مانده) ✅ • E2E-02 (سفارش→حواله با COGS 6M→فاکتور درآمد 9M→
دریافت→۴ سند posted تراز) ✅ • E2E-03 (BOM ۲برد+۱قاب→WO ۵ دستگاه→مصرف
۱۲.۵M→سند WIP→تکمیل→رسید محصول @2.5M→سند محصول/WIP→گزارش بها) ✅ •
E2E-04 (کارکرد+اضافه‌کاری→۲ فیش→سند هزینه ۳۴۴M→پرداخت→سند پرداختنی/بانک +
national_id رمزنگاری‌شده) ✅ • E2E-05 (بستن دوره→سند بستن posted→ماندهٔ
موقت‌ها صفر→**ترازنامه متوازن** ۹M=۰+۹M→سند جدید در دورهٔ بسته رد) ✅ •
E2E-06 (۲۵۰M→صف مالی→تأیید→approved • ۹۰۰M→مالی→صف CEO→تأیید •
۴۰۳های مجوز • رد با دلیل • نمونهٔ واقعی workflow در `tvn_process_instances`) ✅ •
E2E-07 (voucher.created/posted/voided در tvn_audit_logs→verify intact→
tamper→intact=false) ✅ • E2E-08 (**جاروی قانون طلایی**: همهٔ اسناد جریان
مختلط ۶ بافته debit===credit + جمع ردیف‌ها + تراز آزمایشی + رد سند ناتراز
سرویسی و HTTP) ✅ • AC-ERP-11 (رویداد در tvn_outbox ثبت→done؛ pending صفر) ✅

> انحراف‌های مستند: پول int minor-unit (ADR-0013 — به‌جای DECIMAL(18,4)) و
> مقدارها decimal(18,3) • **یک ماژول با ۶ بافتهٔ داخلی** به‌جای ۶ ماژول
> (مرزها با پورت/رویداد حفظ — الگوی shop/lms/crm؛ تجزیه‌پذیری با Outbox) •
> Budgeting/Asset (§1.1) و گزارش بودجه-عملکرد در بک‌لاگ 1.0 (خارج از
> E2E-01..08) • FIFO/LIFO در بک‌لاگ (weighted_avg پیاده شده) • فاکتور
> تأمین‌کننده سند جدا صادر نمی‌کند (بدهی در رسید شناسایی می‌شود) •
> `erp:audit-verify` لازم نشد — `tvn audit:verify` موتور همان AC-ERP-10 است.

```yaml
# machine-readable: tavaan.kit.erp.v1 (وضعیت M9)
kit:
  id: tavaan.kit.erp
  status: implemented
  ticket: TVN-0097
  modules: [tavaan.core-user, tavaan.erp]
  routes_admin: [/admin/erp, /admin/erp/accounts, /admin/erp/vouchers, /admin/erp/periods, /admin/erp/inventory, /admin/erp/inventory/movements, /admin/erp/purchases, /admin/erp/sales, /admin/erp/production, /admin/erp/hr, /admin/erp/reports, /admin/erp/audit]
  routes_csv: [/admin/erp/reports/trial-balance.csv, /admin/erp/reports/profit-loss.csv, /admin/erp/reports/balance-sheet.csv, /admin/erp/reports/turnover.csv, /admin/erp/reports/payroll.csv]
  events: [erp.voucher.posted, inventory.stock.received, inventory.stock.issued, procurement.po.approved, procurement.receipt.registered, sales.invoice.issued, sales.payment.received, production.order.completed, hr.payroll.processed, accounting.period.closed]
  consumed_events: [inventory.stock.received, inventory.stock.issued, sales.invoice.issued, sales.payment.received, production.order.completed, hr.payroll.processed]
  workflow: erp-purchase-approval
  outbox: {table: tvn_outbox, inline_relay: config('erp.integration.inline_relay'), relay_command: event:outbox-relay}
  audit_events: 22
  tables: 24
  permissions: 28
  roles: [accountant, finance-manager, warehouse-keeper, procurement-officer, sales-officer, hr-officer, production-planner, auditor, erp-admin, admin]
  tests: {unit: modules/erp/tests/Unit, feature: modules/erp/tests/Feature}
```
