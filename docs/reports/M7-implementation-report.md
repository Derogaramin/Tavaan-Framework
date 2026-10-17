# گزارش پیاده‌سازی M7 — فریمورک تَوان (دامنه)

**تاریخ:** ۲۰۲۶-۱۰-۱۷ • **نگارش:** `0.7.0` • **مخزن:** `Derogaramin/Tavaan-Framework`
**اسناد بالادستی:** DOC-120 (`docs/08-roadmap/milestones.md`) و DOC-121 (`docs/08-roadmap/agent-backlog.md`)
**پیش‌نیازها:** M3 (داده)، M4 (امنیت)، M5 (زیرساخت)، M6 (سرویس‌ها) — همه سبز در نقطهٔ شروع (۹۰۰ تست)

---

## ۱. خلاصهٔ اجرایی

هر **۳ تیکت M7** به‌طور کامل پیاده‌سازی، تست و مستند شد:

| تیکت | موتور | سند | اولویت | تست‌ها | وضعیت |
|---|---|---|---|---|---|
| TVN-0071 | **Workflow (BPM)** | DOC-071 | P2 | ۳۷ | ✅ |
| TVN-0072 | **Report (xlsx خودنوشته)** | DOC-072 | P2 | ۲۳ | ✅ |
| TVN-0073 | **Payment (درگاه‌ها)** | DOC-073 | P2 | ۳۱ | ✅ |

**حجم تحویل:** ~۹٬۳۰۰ خط کد منبع در `engines/*/src/` (۸۵ فایل PHP جدید در موتورها) + ~۲٬۴۰۰ خط تست (۹۱ تست) + قرارداد `core/Contracts/Payment/GatewayInterface` + ۳ `module.json` + ۲ migration (۵ جدول) + ۶ فایل زبان (fa/en) + ۳ Facade جدید (`Workflow`/`Report`/`Payment`) + ۳ دستور CLI + ۳ نمونهٔ اجراشدنی + ۲ بنچمارک. **صفر وابستگی بیرونی** (مطابق DOC-001) — State Machine/Token engine، xlsx (OOXML) و SOAP/XML درگاه‌ها همگی خودنوشته‌اند.

**نتیجهٔ نهایی:** `۹۹۱ تست / ۶۷۰۱ assertion سبز` • lint کامل پاک (۱٬۱۴۴ فایل) • `۴۸/۴۸ موتور فعال` • نسخه `0.6.0 → 0.7.0`.

> تنها شکست مجموعه در زمان نگارش این گزارش (`MediaTest::testResizeThumbnailConvertWhenDriverAvailable`) یک باگ **fixture** بود که در نگارش ۰٫۷٫۱ ریشه‌یابی و رفع شد (base64 «کوچک‌ترین JPEG» با libjpeg سخت‌گیر ناسازگار بود — اکنون fixture با خودِ gd تولید می‌شود): مجموعه اکنون **۹۹۲ تست / ۶۷۰۴ assertion سبز**. جزئیات: `docs/reports/windows-compatibility-fixes.md`.

---

## ۲. ترتیب اجرا (الگوریتم DOC-121 §7)

هر ۳ تیکت هم‌فاز و P2 بودند؛ ترتیب رجیستری DOC-030 رعایت شد: **Workflow → Report → Payment**. دلیل اضافی: Workflow سنگین‌ترین موتور است (LOC ۲۰۰۰، پیچیدگی ★★★★★) و زودتر شروع شد؛ Payment آخر چون به http-client (M5) و event (M1) تکیه دارد و الگوی تست با `Http::fake` پس از Report (که الگوهای exporter دارد) روان‌تر نوشته شد.

---

## ۳. موتور Workflow (TVN-0071)

**مسیر:** `engines/workflow/` — Facade: `Workflow` — binding: `workflow` — جداول: `tvn_workflows` / `tvn_process_instances` / `tvn_process_tasks` / `tvn_process_history`

### ۳٫۱ تعریف فرایند (دو مسیر)
- **DSL** (`ProcessBuilder`) دقیقاً مطابق DOC-071 §3: ‏`start/state/exclusiveGateway/parallelGateway/timerEvent/subProcess/transition` با زنجیرهٔ fluent ‏`assignee/form/meta` روی state (کلاس `StateFluent`) و `guard/action/permission/meta` روی گذار (`TransitionFluent`) — readonly VOها با replace-in-builder بازسازی می‌شوند (تغییرناپذیری حفظ می‌شود).
- **JSON/Schema** (§3.1 — برای Designer): ‏`defineArray/defineJson` با guard رشته‌ای `can:<permission>` و تایمر ISO-8601 (`P3D`).
- **Validation کامل** پیش از ثبت: `missing_start` / `duplicate_state` / `duplicate_transition` / `unknown_state` (با آدرس دقیق `transition:X.to`) / `orphan_state` / `subprocess_missing` / پارس‌پذیری Duration — همه با messageId ترجمه‌شدهٔ fa+en.
- **نسخه‌بندی**: هر `define` نسخهٔ جدید (version++) ثبت و نسخه‌های قبلی را غیرفعال می‌کند؛ **instanceهای در حال اجرا روی نسخهٔ تعریف خود می‌مانند** (workflow_id → ردیف نسخه).

### ۳٫۲ Execution Engine (مطابق §4 — با اتمیکیتی افزودی)
`fire()` دقیقاً همان شش گام سند: guard → authorize (RBAC) → actions → حرکت token → history+event → complete. دو تضمین افزوده:
- 🔒 **اتمیک**: ‏`snapshot/restore` — هر استثنا (guard/action/gateway) وضعیت توکن/متغیر/تایمر را به حالت پیش از fire برمی‌گرداند (تست: state پس از guard رد‌شده تغییر نمی‌کند).
- 🔒 **auto-refresh**: متدهای تغییردهنده (`fire/fireTimer/complete/cancel/advanceSubProcess`) پیش از اجرا از store همگام می‌شوند — دستهٔ کهنه (مثلاً پس از `Task::complete` از دسته‌ای دیگر) به‌جای noToken خطای درست `not_running` می‌دهد؛ `refresh()` عمومی هم برای خواندن صریح هست.

### ۳٫۳ Token و دروازه‌ها (Fork/Join)
- توکن‌ها در کلید رزرو `_tokens` داخل ستون `variables` (schema سند دست‌نخورده ماند) + `_timers/_joins/_ended/_last_end/_parent/_children`. 🔒 کلیدهای `_` از بیرون قابل نوشتن نیستند (`setVariable` → `InvalidArgumentException`).
- **دروازهٔ موازی**: fork = یک توکن per خروجی؛ join = شمارش ورودی‌ها با توکنِ «منتظر» روی خود join — هنگام تکمیل، توکن‌های منتظر **حذف** می‌شوند (باگ نشت توکن حین تست کشف و رفع شد) و تنها خروجی مسیریابی می‌شود. ورود چندبرابر + خروج چندبرابر = join-then-fork.
- **دروازهٔ انحصاری**: اولین گذار با guard موفق به ترتیب تعریف؛ هیچ‌کدام → `gateway_stuck`.
- 🔒 گذار خروجی دروازه **فقط توسط موتور** اجرا می‌شود (fire دستی → `gateway_manual_fire`) — جلوی شکستن توازن join گرفته می‌شود.
- **پایان**: وقتی همهٔ توکن‌ها به End برسند (ended>0 && tokens=[]) → `complete()`.

### ۳٫۴ Human Task (§7)
- ورود توکن به `user_task` → رکورد `tvn_process_tasks` با assignee پارس‌شده (`role:X`/`user:N`) + رویداد `workflow.task.created` (شیء + رشته).
- `claim/complete/delegate` — claim برای نقش با **RBAC واقعی** (`RoleManager::hasRole` روی tvn_role_user) و 🔒 **fail-closed** بدون RBAC؛ `complete` با transition صریح، `input['transition']` یا تک‌خروجی (چند خروجی بدون تعیین → `task_ambiguous_outgoing`).
- **inbox نقش‌آگاه**: ‏`Workflow::tasksFor($userId)` = کارهای مستقیم `user:` + کارهای نقش‌هایی که کاربر دارد (تست با `RoleManager::assign` واقعی).

### ۳٫۵ Timer (§6)
- پارس مدت دوگانه: انسانی (`3 days` → seconds-relative) و ISO-8601 (`P3D/PT4H`) — نامعتبر → `workflow.invalid_duration`.
- ثبت خودکار با ورود توکن به state و **لغو خودکار با ترک state** (تست: پس از review دیگر auto_reject اجرا نمی‌شود).
- `TimerProcessor::process()` روی batch از instanceهای running — 🔒 یک تایمر خراب بقیه را متوقف نمی‌کند (log + continue)؛ CLI ‏`workflow:timers` (برای `Schedule::command(...)->everyMinute()`).

### ۳٫۶ Sub-Process و Visualizer
- state نوع `sub_process` با ارجاع `process:` → فرزند با کپی متغیرها + پیوند `_parent` شروع می‌شود؛ تکمیل فرزند → `onChildCompleted` → والد از **تک‌خروجی** خودکار عبور می‌کند (چند خروجی → انتظار برای fire دستی). 🔒 خطای پیش‌برد والد هرگز تکمیل فرزند را rollback نمی‌کند.
- **Visualizer (AC-WF-11)**: ‏`toDot` (Graphviz — shape per نوع: circle/doublecircle/diamond/box3d، یال timer خط‌چین نارنجی) و `toMermaid`؛ در `workflow:list --diagram=` هم قابل گرفتن است.

### ۳٫۷ Store دوگانه و محافظ‌ها
- `DatabaseStore` (Query Builder — prepared، JSON encode/decode داخلی) و `ArrayStore` (تست/بدون DB) پشت `StoreInterface`؛ driver از config (`workflow.driver`) با fallback امن به array وقتی DB binding نیست.
- 🔒 DoS: ‏`max_active_instances` per workflow؛ 🔒 نشت متغیر: ‏`maskVariables()` برای نمایش/لاگ (substring: password/token/secret/card/cvv/otp).

---

## ۴. موتور Report (TVN-0072)

**مسیر:** `engines/report/` — Facade: `Report` — binding: `report` — قالب view: ‏`report::table`

### ۴٫۱ Dataset و فیلترها
- چهار شکل dataset: ‏**Closure** (lazy — DOC §3)، **QueryBuilder** (فیلترها → SQL prepared)، **SQL خام**، **آرایه**. خروجی: `{rows, truncated}`.
- 🔒 **allowlist دوطرفه**: فقط فیلترهای اعلام‌شده اعمال می‌شوند (کلید ناشناخته در `run()` نادیده)؛ orderBy هم فقط روی فیلد اعلام‌شده با regex شناسه (`^[A-Za-z_]\w*$`) — مسیر SQL هرگز مقدار/ستون خام کاربر را الحاق نمی‌کند (LIKE با escape `\ % _`).
- `Filter::normalize`: ‏daterange (آرایهٔ `[from,to]` یا `{from,to}` + نیم‌روز خودکار `00:00:00/23:59:59`)، select با **اعتبارسنسی options** (پشتیبانی `Enum::cases()` — مقدار خارج فهرست → رد بی‌صدا)، text/number/bool.
- **derive**: فیلد محاسبه‌شده `fn(array $row): mixed` (ستون `month` برای pivot بدون تغییر dataset).

### ۴٫۲ تجمیع و Pivot
- تجمیع‌ها (sum/avg/count/min/max) با **`Decimal` موتور compat** — جمع مبالغ بدون خطای float (ADR-0013)؛ خروجی int وقتی صحیح.
- **ترتیب درست محاسبات**: totals/subtotals/pivot روی **ردیف‌های خام** (پیش از projection) — فیلد ستون pivot می‌تواند خارج از فیلدهای نمایشی باشد (مثال `columns: 'month'` در DOC با Closure بدون آلودگی خروجی).
- `groupBy + subtotal` → `subtotals(): {group: {field: value}}`؛ **PivotBuilder** مطابق §6 (ستون‌های sort‌شده، سلول خالی = null، row/column از فیلد یا Closure).

### ۴٫۳ خروجی‌ها
- **CSV** (§5 کامل): 🔒 BOM (`EF BB BF`)، delimiter `;`، **streaming تکه‌ای** با `fflush` per chunk (بنچ ۱۰۰k ردیف: سربار حافظه ~0)، 🔒 **ضد CSV Injection** — سلول شروع‌شده با `= + - @` با `'` prefix (در xlsx هم اعمال می‌شود).
- **xlsx خودنوشته** (§4 — بدون PhpSpreadsheet/ext-zip): ‏`XlsxWriter` با `ZipWriterPure` موتور compat — `[Content_Types].xml`/`_rels`/`workbook`/`styles` (سربرگ Bold+fill، **freeze pane** سطر اول)/`sharedStrings` (dedup)/`sheet1`؛ عدد خام int/float → سلول عددی (بدون quote)؛ `colName` از A تا ZZ+؛ 🔒 strip کاراکترهای کنترل نامعتبر XML + escape کامل؛ نام sheet sanitize (≤31، بدون `:\/?*[]`). **تست: با ZipArchive باز و ساختار sheet/sharedStrings/styles تأیید شد (AC-RPT-10)**.
- **HTML**: قالب `report::table` (namespace موتور روی ViewFactory در boot — RTL، totals در tfoot، subtotals و pivot جدا، هشدار truncated) + **fallback خودساختهٔ fully-escaped** بدون موتور View؛ 🔒 تست XSS: ‏`<script>` هرگز خام بیرون نمی‌آید. `render()` → `Response` موتور http.
- **JSON**: ‏`{name, headers, rows(formatted), totals, subtotals, pivot, count, truncated}` با `JSON_UNESCAPED_UNICODE`.
- **PDF اختیاری**: بدون `pdf_driver` → `report.pdf_not_configured`؛ driver=`html` → HTML آمادهٔ چاپ (`@page`/`@media print`)؛ driver ناشناخته → `pdf_driver_unsupported`.
- 🔒 **sandbox مسیر export**: مقصد باید داخل base path بماند (probe روی ریشهٔ موجود + realpath) — `/tmp/evil.csv` → `report.path_not_allowed`.
- 🔒 **max_rows**: سقف از config (پیش‌فرض ۱۰۰k) یا `limit()` صریح → QB با `maxRows+1` fetch می‌کند تا `truncated` بدون count جداگانه درست باشد.

---

## ۵. موتور Payment (TVN-0073)

**مسیر:** `engines/payment/` — Facade: `Payment` — binding: `payment` — جدول: `tvn_payment_transactions` — قرارداد: `core/Contracts/Payment/GatewayInterface`

### ۵٫۱ Contract و ۱۰ درگاه
`GatewayInterface` (purchase/verify/refund/inquire/name) + DTOهای readonly سند (§3) با `Money` (ADR-0013). همهٔ درگاه‌ها از `AbstractHttpGateway` ارث می‌برند: Factory مشترک http-client (🔒 SSRF guard + `Http::fake` در تست)، `assertConfigured()` **پیش از هر تماس شبکه** (نبود اعتبارنامه → `gateway_not_configured` با فهرست کلیدها)، تبدیل `toToman/toRial` (🔒 تومان نه ریال برای زرین‌پال؛ ریال برای سامان/ملت/…)، `maskPan`، پارسر سبک XML (بدون ext-dom/soap).

| درگاه | جزئیات پیاده‌سازی |
|---|---|
| **زرین‌پال** | v4 `payment/request.json` + `verify.json` + `refund.json`؛ کد ۱۰۰/۱۰۱؛ redirect `payment.zarinpal.com/pg/StartPay/{authority}` (sandbox → `sandbox.zarinpal.com`)؛ نگاشت کدهای لغو → Canceled |
| **آیدی‌پی** | `webapp/request|verify|refund|inquiry` با `X-API-KEY`/`X-SANDBOX`؛ 🔒 **IPN با HMAC-SHA256** (`X-Idpay-Signature` با `hash_equals`) |
| **پی‌پینگ** | Bearer token؛ `/pay` → code → `startipg?code=`؛ `/pay/verify` با refId از callback |
| **سامان/SEP** | `GetToken` → `sep.ir/Payments/Redirect?Token=`؛ `Verify` با RefNum + 🔒 مبلغ ریالی محلی |
| **به‌پرداخت ملت** | **SOAP/XML خودنوشته** (بدون ext-soap): ‏PayRequest → `startpay.mellat?RefId=`؛ verify دومرحله‌ای PayVerification+SaleConfirm با **idempotency کد ۴۳** (settle شده) |
| **پارسیان** | SOAP: ‏SalesPaymentRequest → `Token`؛ ConfirmPayment → CardNumberMasked/TraceNo؛ PaymentReversal |
| **سداد / ملی** | payload امضاشده `base64(json).hmac256` با `sign_key` |
| **استرایپ** | Checkout Sessions (form-encoded + Bearer)؛ verify با `payment_status`؛ refund با payment_intent |
| **کیف پول داخلی** | `WalletStoreInterface` (binding `payment.wallet`؛ پیش‌فرض `ConfigWalletStore` برای dev) — 🔒 **کسر موجودی فقط در verify** نه purchase |

### ۵٫۲ جریان تراکنش و امنیت (§4/§7 — ستون فقرات موتور)
- **purchase**: ساخت Transaction ‏`pending` با authority + ذخیرهٔ redirect/meta در `callback_payload` → رویداد `payment.initiated`.
- 🔒 **Idempotency**: کلید = `meta['idempotency_key']` یا `sha256(gateway|orderId|amount|currency)` با `UNIQUE uk_idem`؛ replay روی pending → **همان authority بدون تماس با درگاه** (تست: assertSentCount=1)؛ تراکنش موفق → `payment.already_paid`؛ ناموفق → کلید جدید برای تلاش مجدد.
- 🔒 **verify با مبلغ محلی**: ‏`VerifyRequest.amount` همیشه `tx->money()` است — دستکاری `amount` در callback در تست اثبات کرد که بدنهٔ verify با مبلغ DB می‌رود (Amount tampering).
- 🔒 **عدم اعتماد به Status بازگشتی**: ‏`handleCallback` حتی با `Status=OK` حتماً از API درگاه verify می‌کند (تست callback جعلی: درگاه code=-1 → failed).
- 🔒 **Double payment**: verify روی تراکنش موفق → نتیجهٔ ذخیره‌شده بدون تماس شبکه و بدون رویداد تکراری؛ callback دوم → `alreadyProcessed=true`.
- 🔒 **PAN mask**: ‏`603799****1234` در PaymentResult و DB (تست‌های mask با جداکننده‌های `-`/فاصله).
- 🔒 **IPN**: ‏`notify()` با بررسی امضا (فعلاً IDPay) → ۴۰۳ `signature_mismatch` روی امضای بد (smoke زنده تأیید کرد).
- **AC-PAY-10**: درگاه جدید فقط با `extend(name, factory|class)` یا config `class` — تست با `FakeGateway` (بدون هیچ تغییری در موتور).

### ۵٫۳ لایهٔ وب
routeهای `ANY /payment/callback` (302 → `result_url` با **وضعیت پس از verify**) و `POST /payment/notify/{gateway}` (JSON) در boot provider (الگوی realtime/M6) + **استثنای CSRF** برای این دو مسیر در `engines/session/config/session.php` — redirect درگاه‌ها cross-site POST بدون توکن جلسهٔ ماست (اصالت با verify+امضا تضمین می‌شود، نه CSRF)؛ همان قرارداد M6 برای `api/*`.

---

## ۶. تغییرات cross-engine (حداقلی و هدفمند)

| فایل | تغییر |
|---|---|
| `config/providers.php` | +۳ provider (workflow/report/payment) |
| `core/Facade/Facades/{Workflow,Report,Payment}.php | ۳ Facade جدید با `@method`های کامل |
| `core/Contracts/Payment/GatewayInterface.php` | قرارداد عمومی (الگوی SearchManagerInterface — ارجاع DTOهای موتور) |
| `engines/session/config/session.php` | 🔒 استثنای CSRF برای `payment/callback` و `payment/notify/*` |
| `.env.example` | بخش M7 (workflow/report/payment + اعتبارنامهٔ ۱۰ درگاه) |
| `tavaan.json` | نسخه `0.6.0 → 0.7.0` |
| `resources/lang/{fa,en}/{workflow,report,payment}.php` | ۶ فایل زبان (همهٔ messageIdها کلید دارند) |
| `docs/08-roadmap/agent-backlog.md` | TVN-0071/72/73 → done + dashboard (۵۰ done / ۱۳ todo) |

هیچ موتور موجودی تغییر رفتار نداد (تنها افزودن استثنای CSRF که برای webhookها لازم بود).

---

## ۷. باگ‌های ریشه‌ای که حین پیاده‌سازی/تست کشف و رفع شد

1. **Workflow — status در rehydrate از دست می‌رفت**: `manager->instance()` وضعیت را از ردیف DB نمی‌خواند و instance بازسازی‌شده همیشه `running` بود (تکمیل/لغو پس از reload دیده نمی‌شد) — پارامترهای `status/completedAt` به سازنده افزوده شد.
2. **Workflow — نشت توکن در join**: هنگام تکمیل join، توکنِ «منتظرِ» شاخهٔ اول در فهرست توکن‌ها می‌ماند و فرایند هرگز کامل نمی‌شد — حذف `count(incoming)-1` توکن منتظر پیش از مسیریابی خروجی.
3. **Workflow — ترتیب history**: record گذار محرک **پس از** مسیریابی خودکار دروازه ثبت می‌شد (approve پیش از review در history!) — ثبت پیش از `enterState` منتقل شد (fireTimer هم همسان).
4. **Workflow — دستهٔ کهنه (stale handle)**: ‏`Task::complete` روی instance تازه fire می‌کند و دستهٔ قدیمی کاربر کهنه می‌ماند — `refresh()` + همگام‌سازی خودکار پیش از هر متد تغییردهنده (تست رگرسیون: `not_running` به‌جای `noToken`).
5. **Payment — بازنویسی callback_payload**: ‏`updateFromResult` کل payload را با raw درگاه جایگزین می‌کرد → `meta` خرید (از جمله `user_id` کیف پول) می‌پرد و **refund کیف پول شکست می‌خورد** (با نمونهٔ اجراشدنی کشف شد) — ادغام به‌جای جایگزینی (`payload['verify'] = raw`).
6. **Payment — redirect با وضعیت کهنه**: ‏`resultUrl` پیش از verify ساخته می‌شد → کاربر با `status=pending` به صفحهٔ نتیجه می‌رفت — ساخت redirect پس از verify.
7. **Report — avg در pivot**: تجمیع افزایشی scalar برای avg نادرست بود — بازنویسی به جمع‌آوری مقادیر هر سلول + تجمیع پایانی.
8. **Migration — انواع کلید**: ‏`$t->id()` در این فریمورک `bigIncrements` است (نه ULID) → datatype mismatch روی SQLite؛ به الگوی رایج مخزن `$t->char('id',26)->primary()` تبدیل شد. همچنین **نام ایندکس در SQLite سراسری است** — `idx_instance` تکراری در دو جدول → نام‌گذاری خودکار Blueprint.
9. **CSRF روی IPN (smoke زنده)**: ‏`POST /payment/notify` → ۴۱۹ — استثنای `payment/*` افزوده شد (§5.3).
10. **alias در تست**: ‏`Container::instance()` برخلاف `make()` alias را resolve نمی‌کند — bind استاب auth باید روی `AuthManager::class` بنشیند نه `'auth'` (در تست‌ها اعمال شد).

---

## ۸. راستی‌آزمایی — تست، Smoke و بنچ

### ۸٫۱ تست‌ها (۹۱ تست جدید — همهٔ T-های اسناد)
- **Workflow (۳۷)**: ‏T-01 تعریف+validation (۴ خطای ساختاری) • T-02 guard رد + اتمیک بودن • T-03 هر دو شاخهٔ دروازهٔ انحصاری (۵M→approved / ۱۵M→escalate) • T-04 fork (۲ توکن) + join (انتظار/آزادسازی) + 🔒 fire دستی از دروازه • T-05 task با assignee نقش (RBAC واقعی: create+assign) + claim رد/قبول + delegate + complete • T-06 تایمر (سررس/لغو با ترک state) + Duration (انسانی/ISO/نامعتبر) • T-07 history با ترتیب و user_id • T-08 permission (بدون کاربر/بدون مجوز/با مجوز + can بدون side effect) • T-09 sub-process (والد-فرزند) • T-10 JSON definition با `can:` و `P3D` • AC-WF-11 visualizer (DOT/Mermaid) • رویدادهای چرخهٔ عمر • محافظ‌ها (max_instances/mask/reserved keys/not_running/cancel) • ArrayStore بدون DB • action/derive/notifyAssignee • stale-handle/refresh.
- **Report (۲۳)**: ‏T-01 آرایه+QB (فیلترهای text/select/daterange/number، allowlist، enum options، orderBy) • T-02 groupBy+subtotal • T-03 pivot (sum با ستون Closure) • T-04 CSV (BOM/delimiter/جلالی/عدد raw) • T-05 xlsx (ZipArchive باز می‌شود + sheet/sharedStrings/bold header/سلول عددی + colName AA/BA) • T-06 CSV injection (هر ۴ نویسهٔ خطرناک) • T-07 streaming 10k (ژنراتور chunked + شمارش خطوط خروجی) • T-08 max_rows/limit + truncated • T-09 HTML (escape شده + جلالی + جمع کل) • JSON/PDF (not_configured + driver html)/format نامعتبر/sandbox مسیر/SQL خام/dataset غایب/aggregate نامعتبر.
- **Payment (۳۱)**: ‏T-01 purchase (redirect + persist pending + رویداد) + replay idempotent (۱ تماس) + already_paid + failure persist • T-02 verify (success/۱۰۱/canceled) + idempotent (بدون تماس دوم) • T-03 callback دو بار = یک اثر • T-04 amount tampering (بدنهٔ verify = مبلغ محلی) • T-05 callback جعلی + authority ناشناخته • T-06 refund (+رد refund دوباره) • T-07 PAN mask (۴ حالت) • T-08 چند درگاه با یک contract (wallet چرخهٔ کامل + کسر/بازگشت موجودی، idpay هدرها، stripe) + IPN با امضای HMAC (رد/قبول) • T-09 sandbox • AC-PAY-10 extend + gateway نامعتبر/پیکربندی‌نشده + inquire + persist در DB • ۶ تست مسیرهای XML/SOAP (behpardakht خرید/verify/ResCode بد، saman، parsian، payping، sadad امضاشده).

### ۸٫۲ Smoke زنده
| سناریو | نتیجه |
|---|---|
| `tvn about` | ✅ `48/48 موتور فعال` — boot ۳۵ms |
| `tvn migrate` (+`migrate:status`) | ✅ ۱۴ migration — دو تای M7 با attrib درست (`engine:workflow`/`engine:payment`) |
| `tvn workflow:timers` / `workflow:list` / `payment:list` | ✅ خروجی سالم روی DB خالی |
| `php bin/tvn serve` → `GET /` | ✅ 200 |
| `GET /payment/callback?Authority=GHOST&Status=OK` | ✅ 404 `{"error":"payment.not_found"}` |
| `POST /payment/notify/idpay` (بدون امضا) | ✅ 403 `{"error":"signature_mismatch"}` |
| `POST /payment/callback` (form، authority ناشناخته) | ✅ 404 JSON |
| `examples/workflow/order-approval.php` | ✅ draft→pending_review→approved→completed + history + DOT |
| `examples/report/sales-report.php` | ✅ CSV (BOM/;)+xlsx در storage/temp |
| `examples/payment/wallet-checkout.php` | ✅ purchase→verify→کسر موجودی→refund→بازگشت موجودی |

### ۸٫۳ بنچمارک (اهداف DOC-072 §9)
```
html 10k rows        102.0 ms  (≤ 2000 ms) ✅      xlsx 10k rows   104.5 ms (≤ 3000 ms) ✅
pivot 10k (3×50)      55.6 ms  (≤ 1000 ms) ✅      csv stream 100k: سربار 0.00 MB (≤ 5 MB) ✅
workflow: build 30-state 1.24ms (≤5ms) ✅ • start 13.1µs • fire 7.6µs/op ✅
```

---

## ۹. محدودیت‌های شناخته‌شده (صداقت مهندسی)

1. **درگاه‌های بانکی واقعی آزموده نشدند** — sandbox هیچ merchant_id واقعی ندارد؛ zarinpal/idpay/payping/saman/parsian/behpardakht/sadad/stripe/wallet همگی با `Http::fake` روی **شکل مستندشدهٔ API** آزموده شدند (XML/SOAP round-trip شامل). پیش از production، هر درگاه باید با اعتبارنامهٔ sandbox واقعی خود بانک راستی‌آزمایی شود (endpoint/کد خطاها ممکن است نسخه‌به‌نسخه تفاوت کند — همه از config قابل override).
2. **inquire** برای درگاه‌های بدون API استعلام (زرین‌پال/پی‌پینگ/…) پیش‌فرض `Pending` برمی‌گرداند (🔒 محافظه‌کارانه — هرگز حدس موفقیت)؛ sadad/melli/behpardakht نیازمند استعلام سمت بانک با مرجع تراکنش‌اند.
3. **timer اسکن instanceهای running است** (batch از config) — در مقیاس خیلی بزرگ (میلیون instance فعال) جدول تایمر ایندکس‌شدهٔ مجزا بهینه‌تر است؛ schema سند (§5) عمداً دست‌نخورده ماند و `_timers` در JSON variables است.
4. **join توازن چنددور (loop) را نمی‌شمارد** — شمارش per ورودی است؛ فرایندهای چرخه‌ای با join در حلقه باید با نسخهٔ بعدی BPM (token id دار) مدل شوند.
5. **xlsx فقط write است** (مطابق DOC-072 §12 — «خودنوشته، فقط write»)؛ خواندن xlsx در scope نبود. ساختار با ZipArchive تأیید شد ولی باز شدن در Excel/LibreOffice واقعی در sandbox ممکن نیست (OOXML مطابق spec تولید می‌شود — styles/sharedStrings/freeze pane استانداردند).
6. **streaming گزارش** سربار export را ثابت نگه می‌دارد ولی dataset پیش از آن در حافظه materialize می‌شود (totals/pivot به کل داده نیاز دارند) — ژنراتور سرتاسری برای datasetهای عظیم‌تر از max_rows در scope نبود (max_rows همین حالا حافظه را کران می‌دهد).
7. **Stripe با IRT/IRR کار نمی‌کند** (محدودیت خود Stripe) — currency باید ارزی باشد که Stripe پشتیبانی کند؛ درگاه‌های ایرانی IRT/IRR را کامل پوشش می‌دهند.
8. **refund زرین‌پال** طبق API v4 با مبلغ منفی فراخوانی می‌شود؛ برخی merchantها نیاز به تأیید پشتیبانی دارند (خارج از کنترل کد).
9. **PDF** عمداً driver واقعی ندارد (DOC-072: اختیاری) — مسیر `html` خروجی آمادهٔ چاپ می‌دهد؛ افزودن driver (مثلاً wkhtmltopdf exec) با یک Closure در آینده ساده است.

---

## ۱۰. گام بعدی — M8 (ابزار)

طبق DOC-121 §7 تیکت‌های آماده: **TVN-0077 Scaffold** ، **TVN-0078 Admin**، **TVN-0079 Plugin**، **TVN-0084 Test Framework**، **TVN-0081 Upgrade** — پیش‌نیاز «همهٔ فازها» اکنون کامل است (M7 بسته شد). موتور `test` پایهٔ موجود دارد (Native Runner) و TVN-0084 آن را به framework کامل ارتقا می‌دهد.
