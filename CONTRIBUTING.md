# مشارکت در تَوان

1. **Docs-First**: ابتدا مستند موتور (DOC-150)؛ اگر کد با سند در تضاد است، اول سند اصلاح شود (یا ADR).
2. **Contract-First**: هر API عمومی → ابتدا Interface در `core/Contracts/`.
3. **TDD**: معیار پذیرش (AC-*) را قبل از کد به تست تبدیل کنید.
4. هر فایل: `declare(strict_types=1)`، یک کلاس در هر فایل، بدون `?>` پایانی.
5. استثنا: `messageId` + `context` + `hint` (نه متن خام).
6. قبل از PR:
   ```bash
   bash tools/scripts/lint.sh
   php tools/scripts/check-forbidden.php
   php bin/tvn test
   ```
7. بنچمارک موتور خود را در `engines/<x>/benchmarks/` نگه دارید.

قواعد کامل: `docs/01-standards/` و `docs/07-quality/definition-of-done.md`.
