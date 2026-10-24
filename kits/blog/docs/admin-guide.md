# راهنمای مدیریت کیت وبلاگ 🛠️

## ورود به پنل

پیشخوان مدیریت وبلاگ در `/admin/blog/posts` قرار دارد (پشت احراز هویت).
نقش‌های مجاز: `author` (فقط مقالات خود)، `editor` و `admin` (همه).

## جریان کار محتوا

1. **مقالهٔ جدید** — عنوان/بدنه (Markdown)/دسته/برچسب‌ها را پر کنید.
   - «وضعیت»: پیش‌نویس | منتشرشده | زمان‌بندی‌شده
   - برای زمان‌بندی: وضعیت را `scheduled` و «زمان انتشار» را پر کنید.
2. **انتشار زمان‌بندی‌شده** را cron فعال کنید:
   `* * * * * php bin/tvn blog:publish-scheduled`
3. **SEO** — فیلدهای seo_title/seo_description در فرم؛ Open Graph و
   sitemap به‌صورت خودکار ساخته می‌شوند.
4. **تصویر شاخص** — ULID رکورد `tvn_media` (آپلود با موتور media).

## Moderation نظرات

- `/admin/blog/comments` — فهرست با فیلتر وضعیت (pending/approved/spam).
- نظرات مهمان به‌صورت پیش‌فرض `pending` هستند (config `comment.moderation.mode`).
- نویسندهٔ دارای سابقهٔ تأیید، در صورت `auto_approve_trusted` به‌صورت خودکار تأیید می‌شود.
- هرزنامه: بیش از `max_links` لینک یا واژهٔ مسدود → خودکار `spam`.

## اعلان‌ها

- نظر جدید → ایمیل + اعلان پایگاه‌داده به نویسندهٔ مقاله.
- تأیید نظر → ایمیل به نویسندهٔ نظر (AnonymousNotifiable).
- خاموش/روشن: `config('blog.comments.notify_author|notify_commenter')`.

## presetها و theme

- تغییر preset: `php bin/tvn kit:install blog --preset=enterprise` (idempotent).
- override پوسته بدون تغییر ماژول: ویوها را در
  `resources/views/vendor/blog/` کپی و ویرایش کنید (اولویت بالاتر از theme کیت).
