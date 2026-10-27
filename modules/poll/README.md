# ماژول نظرسنجی (tavaan.poll) 📊

نظرسنجی کامل عمومی/مدیریتی — پرسش + گزینه‌ها + پنجرهٔ زمانی رأی‌گیری، رأی یکتا
(کاربر/هش IP)، نتایج درصدی و JSON API.

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.poll` |
| جدول‌ها | `poll_polls`, `poll_votes` (prefix: `poll_`) |
| معماری | Hexagonal: Domain (خالص) → Application (UseCase) → Infrastructure (DB Facade) |
| وابستگی ماژولی | `tavaan.content` (فقط VO مشترک `Slug`) |
| مجوزها | `poll.view` / `poll.create` / `poll.update` / `poll.open` / `poll.delete` |

## مسیرها

| متد | مسیر | توضیح | مجوز |
|---|---|---|---|
| GET | `/polls` | فهرست نظرسنجی‌های باز | — |
| GET | `/polls/{slug}` | فرم رأی یا نتایج (طبق `poll.results`) | — |
| POST | `/polls/{slug}/vote` | ثبت رأی (وب یا JSON با `expectsJson`) — throttle ۱۰/۱۰دقیقه | — |
| GET | `/api/polls/{slug}` | JSON: متادیتا + نتایج | — |
| GET/POST/DELETE | `/admin/polls…` | CRUD + `/{id}/open` + `/{id}/close` | `poll.*` |

## اجزا

| کلاس | نقش |
|---|---|
| `Poll` (Entity) | Aggregate ریشه: چرخهٔ `draft→open→closed` (+بازگشایی)، پنجرهٔ زمانی، soft-delete، رویدادها |
| `PollStatus` (Enum) | ماتریس انتقال + `isPublic()` (open/closed عمومی، draft نه) |
| `Vote` (DTO) | یک ردیف رأی؛ چندانتخابی = چند ردیف |
| `VoteTally` (Service خالص) | شمارش + درصد (یک اعشار) + گزینهٔ پیشتاز |
| `PollWriteService` | create/update/open/close/trash/forceDelete — **قفل گزینه‌ها پس از نخستین رأی** |
| `VoteService` | cast/hasVoted — یکتایی user/ip_hash، اعتبار گزینه‌ها، تک/چندانتخابی |
| `PollReadService` | publicBySlug/listOpen/results/shouldShowResults |
| `DatabasePollRepository` / `DatabaseVoteRepository` | آداپتر DB (فقط Facade — ARCH-003) |
| `PollsWidget` | ویجت داشبورد (بازها + کل رأی‌ها) |

## 🔒 قواعد کلیدی

- **رأی یکتا**: کاربر واردشده با `user_id`؛ مهمان با `ip_hash = sha256(ip|app.key)` —
  **IP خام هرگز ذخیره نمی‌شود** (حریم خصوصی).
- **انقضا بدون cron**: `Poll::isVotable()` زمان‌محور است؛ `ends_at` گذشته ⇒ رأی‌پذیر نیست
  (ولی `open` می‌ماند تا دستی بسته شود).
- **قفل گزینه‌ها**: پس از نخستین رأی، تغییر گزینه‌ها `ValidationException`
  (`poll::messages.options_locked`) می‌دهد — صحت جمع‌بندی نتایج.
- **نمایش نتایج**: `poll.results` = `after_vote` (پیش‌فرض) | `always` | `closed`.

## پیکربندی — `config('poll.*')`

```php
'enabled'     => env_bool('TVN_POLL', true),
'per_page'    => 10,  'admin_per_page' => 15,
'min_options' => 2,   'max_options'    => 10,
'voting'      => ['guest_allowed' => true, 'unique_by' => ['user', 'ip']],
'results'     => 'after_vote',
'throttle'    => '10,10',
```

## رویدادها

| رویداد | کلاس/payload |
|---|---|
| `poll.opened` | `PollWasOpened` |
| `poll.closed` | `PollWasClosed` (با totalVotes) |
| `poll.vote.cast` | payload (بدون کلاس) |
| `poll.deleted` | payload (بدون کلاس) |

## ترجمه

`poll::messages.*` و `poll::status.draft|open|closed` در `lang/{fa,en}/`.

## تست

```bash
php bin/tvn test modules/poll
```

پوشش: ماتریس وضعیت/رویدادها، پنجرهٔ زمانی، VoteTally (درصد/پیشتاز/تساوی)،
slug یونیک، قفل گزینه‌ها، یکتایی رأی (user/ip)، E2E پنل با RBAC واقعی،
رأی وب/JSON و 404ها.

## 🔒 مرز معماری (ARCH-003)

بدون ارجاع به `Tavaan\Engine\*` در src (فقط Core Contracts/Facades). استثنا:
فایل‌های routes مثل سایر ماژول‌ها middleware موتورها را با نام کلاس ارجاع می‌دهند
(الگوی تأییدشدهٔ blog/comment).
