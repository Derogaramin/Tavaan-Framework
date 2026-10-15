<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt\Exceptions;

/**
 * کلید نسخهٔ خواسته‌شده در KeyRing موجود نیست.
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class KeyMissingException extends EncryptionException
{
    public static function forVersion(int $version): self
    {
        return new self('crypt.key_missing', ['version' => $version], 'کلید قدیمی را برای rotation در APP_KEY نگه دارید.');
    }

    public static function notConfigured(): self
    {
        return new self('crypt.key_not_configured', [], 'APP_KEY تنظیم نشده؛ `tvn key:generate` را اجرا کنید.');
    }
}
