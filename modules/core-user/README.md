# ماژول کاربران پایه (tavaan.core-user) 👤

ماژول پایهٔ کاربران برای همهٔ کیت‌های M9 — جدول `users`، موجودیت `User`
و قرارداد عمومی `UserReaderInterface` (DOC-090 §6).

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.core-user` |
| پیش‌نیاز موتورها | database, auth, hash, rbac |
| جدول | `users` (بدون prefix — موجودیت پایهٔ اپلیکیشن) |
| مصرف‌کنندگان | همهٔ ماژول‌ها (blog/shop/crm/…) |

## اجزا

| کلاس | نقش |
|---|---|
| `Domain/Entities/User` | Authenticatable + Notifiable (mail→email، sms→mobile، database→id) |
| `Domain/Enums/UserStatus` | active / inactive / banned |
| `Contracts/UserReaderInterface` | پورت عمومی خواندن (snapshot — بدون نشت رمز/توکن) |
| `Contracts/UserSnapshot` | DTO پایدار بین‌ماژولی |
| `Infrastructure/Persistence/DatabaseUserRepository` | آداپتر DB Facade (هش Argon2id با موتور hash) |
| `Console/Commands/UserCreateCommand` | `tvn user:create <email> --name= --password= --role=` |
| `seeds/demo.php` | کاربران demo (admin/editor/author/reader@blog.test — رمز: `password`) |

## سازگاری با موتور auth

`DatabaseUserProvider` موتور auth به‌صورت پیش‌فرض روی جدول `users`
(GenericUser) کار می‌کند — `Auth::attempt/login/logout` بدون پیکربندی
اضافی. موجودیت `User` این ماژول برای دامنه/نمایش و `Notifiable` است.

## مجوزها

`user.{view,create,update,delete}` — اعلام در manifest، sync با `tvn rbac:sync`.

## تست

```bash
php bin/tvn test modules/core-user   # Feature روی SQLite in-memory
```
