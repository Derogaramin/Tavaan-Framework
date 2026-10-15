<?php

declare(strict_types=1);

namespace Tavaan\Engine\Crypt\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * پایهٔ خطاهای رمزنگاری (حوزهٔ کد: CY).
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
class EncryptionException extends TavaanException
{
    public static function encryptFailed(string $error): self
    {
        return new self('crypt.encrypt_failed', ['error' => $error], 'پیکربندی openssl/کلید را بررسی کنید.');
    }
}
