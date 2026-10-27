# TICKET — ماژول پرسش‌های متداول

| بلیت | عنوان | وضعیت |
|---|---|---|
| TVN-FAQ-001 | موجودیت‌های FaqCategory/FaqItem + ماتریس ContentStatus + رویدادها | ✅ انجام شد |
| TVN-FAQ-002 | قراردادها و آداپترهای ماندگاری (DB facade، paginate، LIKE، increment) | ✅ انجام شد |
| TVN-FAQ-003 | FaqWriteService (markdown→sanitize، اسلاگ یونیک، چرخهٔ وضعیت، trash/force) + Provider | ✅ انجام شد |
| TVN-FAQ-004 | رویدادهای کلاس faq.item.published / faq.item.updated + payloadها | ✅ انجام شد |
| TVN-FAQ-005 | کنترلرها: عمومی /faq (+helpful +apiIndex)، ادمین /admin/faqs، /admin/faq-categories | ✅ انجام شد |
| TVN-FAQ-006 | FaqReadService — درخت عمومی، جست‌وجو (mb_stripos)، فیلتر دسته، آمار | ✅ انجام شد |
| TVN-FAQ-007 | UI: layout عمومی/ادمین، faq-index آکاردئونی، faqs-index، faq-form، categories-index + FaqWidget | ✅ انجام شد |
| TVN-FAQ-008 | تست‌ها: FaqDomainTest (۱۶)، FaqWriteServiceTest (۱۶)، FaqHttpTest (۱۵) | ✅ نوشته شد — ⏳ اجرا در انتظار بازیابی mount سندباکس |
| TVN-FAQ-009 | lang fa/en (messages + status) | ✅ انجام شد |

## تصمیم‌های طراحی

1. **رأی مفید = شمارندهٔ انباشته** روی `faq_items` (بدون جدول رأی) — نیاز FAQ
   در حد «چند نفر این را مفید دانستند» است؛ یکتایی per-session با
   `Session::put('faq_helpful_{id}')` (best-effort) + throttle مسیر (۱۰/۱۰دقیقه).
2. **دستهٔ trash‌شده** پرسش‌هایش را «بدون دسته» نشان می‌دهد (حذف دسته ≠ حذف پرسش).
3. **بازگردانی archived→draft** مجاز، **archived→published** طبق ماتریس content ممنوع
   (باید از draft منتشر شود) — اکشن `/admin/faqs/{id}/draft` برای همین افزوده شد.
4. **جست‌وجو** در سرویس با `mb_stripos` روی پرسش + `strip_tags(answer)`؛ فیلتر
   admin با LIKE پارامتری (escape شده برای `%`/`_`) در مخزن.
5. **پاسخ markdown** با همان زنجیرهٔ content: `Markdown::toHtml` (escape-first) +
   `Security::sanitize(rich_text)` — فرم ادمین HTML ذخیره‌شده را نشان می‌دهد
   (رفتار یکسان با page-form ماژول content).
