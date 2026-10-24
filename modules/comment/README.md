# ماژول نظر (tavaan.comment) 💬

دامنهٔ عمومی نظرات برای کیت‌های M9 — **خالص** (بدون جدول/DB/HTTP اختصاصی).

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.comment` |
| مستند مرجع | DOC-090 §6 (CommentableInterface)، DOC-091 §2/§5 |
| مصرف‌کنندگان | `tavaan.blog` (blog_comments)، shop/lms/forum (M9) |

## طراحی (Hexagonal)

ماژول **پورت** تعریف می‌کند، **آداپتر** نه:

```
CommentableInterface   ← موجودیت نظرپذیر (blog Post و …)
CommentStoreInterface  ← پورت ماندگاری؛ پیاده‌سازی در ماژول مصرف‌کننده
                          (blog → جدول blog_comments با prefix خودش — DoD)
CommentModerator       ← سیاست moderation (اسپم/تأیید — config-driven)
CommentThreadBuilder   ← رشتهٔ تو‌در‌تو O(n) با سقف عمق + یتیم→ریشه
Comment (entity)       ← immutable + wither + ULID + linkCount
CommentStatus (enum)   ← pending | approved | spam
```

رویداد عمومی: `comment.status.changed` (v1) — ماژول مصرف‌کننده رویدادهای
دامنهٔ خودش را هم dispatch می‌کند (مثلاً `blog.comment.submitted` — DOC-091 §5).

## پیکربندی — `config('comment.*')`

```php
'moderation' => [
    'mode' => 'manual',              // manual | approve_all
    'auto_approve_trusted' => true,  // نویسنده با سابقهٔ تأیید → auto
    'max_links' => 3,                // لینک بیشتر = اسپم
    'blocked_words' => [],
],
'thread' => ['max_depth' => 8],
```

## ترجمه

`comment::messages.status.*`، `comment::messages.submitted|submitted_approved|closed`.

## تست

```bash
php bin/tvn test modules/comment   # ۱۲ تست / ۳۷ assertion
```

پوشش: ماتریس moderation (۵ سناریو)، اسپم (لینک/واژه)، immutability،
رشتهٔ تو‌در‌تو (جنگل/یتیم/سقف عمق).
