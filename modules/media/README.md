# ماژول رسانه (tavaan.media) 🖼️

لایهٔ **قرارداد/آداپتر** رسانه برای ماژول‌های دامنه (DOC-090 §6) —
جدول/ذخیره‌سازی متعلق به موتور `media` است (`tvn_media`).

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.media` |
| پیش‌نیاز موتورها | media |
| مصرف‌کنندگان | blog (تصویر شاخص)، shop، lms |

## اجزا

| کلاس | نقش |
|---|---|
| `Contracts/MediaAttachableInterface` | موجودیت پیوست‌پذیر (mediaSubjectType/Id) |
| `Contracts/MediaGatewayInterface` | پورت دسترسی (find/attach/delete) |
| `Domain/ValueObjects/MediaFile` | DTO ماژولی (url + variantUrls + isImage) |
| `Infrastructure/FacadeMediaGateway` | آداپتر روی Facade هسته (`Media::`) — بدون ارجاع کلاسی به موتور |

## چرا این ماژول؟

مرز معماری (ARCH-003) ماژول‌ها را از ارجاع مستقیم به موتور media منع
می‌کند؛ این ماژول پورت+آداپتر را فراهم می‌کند تا دامنه (مثلاً `Post`)
فقط به قرارداد وابسته باشد و تست‌پذیری با fake gateway ممکن شود.

## تست

```bash
php bin/tvn test modules/media
```
