<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt\Exceptions;

/**
 * خطای رمزگشایی — شامل tamper detection (MAC/GCM tag نامعتبر).
 *
 * 🔒 پیام خطا هرگز جزئیات plaintext را فاش نمی‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class DecryptException extends EncryptionException
{
    public static function invalidFormat(): self
    {
        return new self('crypt.invalid_format', [], 'قالب payload باید v<n>:iv:ct:tag باشد.');
    }

    public static function invalidPayload(): self
    {
        return new self('crypt.invalid_payload', [], 'payload رمزنگاری‌شده معتبر نیست.');
    }

    public static function decryptFailed(): self
    {
        return new self('crypt.decrypt_failed', [], 'رمزگشایی ناموفق — داده دستکاری شده یا کلید اشتباه است.');
    }
}
