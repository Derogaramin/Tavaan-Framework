# سیاست امنیت تَوان

- 🔒 `make()`/`dispatch()`/`eval` با ورودی مستقیم کاربر ممنوع (DOC-016، DOC-031 §9).
- 🔒 Autoloader: نام کلاس sanitize + containment در basePath + prefixهای هسته قفل‌شده.
- 🔒 `.env` بدون `putenv`؛ خارج از وب؛ `public/` فقط `index.php`.
- 🔒 LogMasker روی همهٔ کانال‌های لاگ؛ `about --json` بدون secret خام.
- 🔒 خطاها در production بدون نشت جزئیات (messageId + refCode).
- گزارش آسیب‌پذیری: derogar@proton.me (اطلاعات عمومی: github.com/Derogaramin)
