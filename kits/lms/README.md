# کیت آموزش آنلاین (LMS) — `kits/lms` (DOC-095 + DOC-090)

نصب کامل سامانهٔ مدیریت آموزش تَوان با یک دستور:

```bash
php bin/tvn kit:install lms --preset=standard --with-demo
```

## آنچه نصب می‌شود

| جزء | جزئیات |
|---|---|
| ماژول‌ها | core-user, content, comment, media, **lms** (blog اختیاری) |
| جداول | ۱۲ جدول `lms_*` + جداول ماژول‌های پیش‌نیاز |
| نقش‌ها | student, instructor, assistant, lms-admin, admin |
| مجوزها | ۱۴ کلید `lms.*` (rbac:sync خودکار) |
| پوسته | `themes/default` → `resources/views/vendor/lms/` + `public/assets/kits/lms/` |
| دمو | ۳ دسته، ۲ دورهٔ منتشرشده (رایگان + پولی) با بخش/درس/آزمون، انتصاب نقش‌ها، ثبت‌نام فعال + دورهٔ تکمیل‌شده با **گواهی قابل verify** |

## Presetها

| Preset | پرداخت | گواهی | حد نصاب آزمون | کش |
|---|---|---|---|---|
| `minimal` | sandbox | صدور خودکار **خاموش** | ۵۰٪ | خاموش |
| `standard` (پیش‌فرض) | sandbox | خودکار با Workflow | ۵۰٪ / ۳ تلاش | ۶۰s |
| `enterprise` | **engine** (درگاه واقعی) | خودکار با Workflow | ۶۰٪ / ۲ تلاش + timer ۳۰ دقیقه | ۱۲۰s |

> preset فقط overlay روی `config/lms.php` می‌نویسد (gitignored) — همیشه قابل
> ویرایش دستی است. برای درگاه واقعی: `lms.payment.driver=engine` +
> `lms.payment.gateway=zarinpal` و کلیدهای API در `.env`.

## مسیرهای کلیدی پس از نصب

- ویترین: `/lms` • جست‌وجو `/lms/search?q=` • دسته `/lms/category/{slug}` • دوره `/lms/courses/{slug}`
- یادگیری: `/lms/learn` (دوره‌های من) • `/lms/learn/{slug}` (پخش) • `/lms/lessons/{id}` • `POST /lms/lessons/{id}/complete`
- آزمون: `/lms/quizzes/{id}` • `POST /lms/quizzes/{id}/attempt` • `POST /lms/attempts/{id}/submit` • کارنامه `/lms/attempts/{id}/result`
- گواهی: **verify عمومی** `/lms/certificates/{serial}` • سند چاپ `/lms/certificates/{serial}/document` • فرم `/lms/verify-certificate`
- پرداخت sandbox: `/lms/pay/{id}` → شبیه‌ساز `/lms/pay/sandbox/{authority}` → `/lms/pay/callback`
- پنل: `/admin/lms/courses|grading|enrollments|certificates|reports/progress`

## کاربران دمو (`--with-demo`)

| ایمیل | رمز | نقش LMS |
|---|---|---|
| admin@blog.test | password | lms-admin |
| author@blog.test | password | instructor (مدرس دوره‌های دمو) |
| editor@blog.test | password | assistant |
| reader@blog.test | password | student (ثبت‌نام فعال + گواهی) |

## راهنماها

- `docs/user-guide.md` — ثبت‌نام تا گواهی (دید دانشجو)
- `docs/admin-guide.md` — ساخت دوره/آزمون، نمره‌دهی، گواهی و گزارش (دید مدرس/مدیر)
