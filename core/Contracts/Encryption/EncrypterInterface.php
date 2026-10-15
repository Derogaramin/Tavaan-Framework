<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Encryption;

/**
 * قرارداد رمزنگار متقارن (DOC-014 §2.8).
 *
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
interface EncrypterInterface
{
    public function encrypt(string $plaintext, string $aad = ''): string;

    public function decrypt(string $payload, string $aad = ''): string;

    public function hmac(string $data, string $context = ''): string;

    public function verifyHmac(string $data, string $mac, string $context = ''): bool;
}
