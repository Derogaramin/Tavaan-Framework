<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade رمزنگاری (Crypt — DOC-054).
 *
 * @method static string encrypt(string $plaintext, string $aad = '')
 * @method static string decrypt(string $payload, string $aad = '')
 * @method static string encryptJson(array $data, string $aad = '')
 * @method static array decryptJson(string $payload, string $aad = '')
 * @method static string hmac(string $data, string $context = '')
 * @method static bool verifyHmac(string $data, string $mac, string $context = '')
 * @mixin \Tavaan\Engine\Crypt\Crypt
 * @api 1.0
 * @see docs/03-engines/crypt.md
 */
final class Crypt extends Facade
{
    protected static function accessor(): string
    {
        return 'encrypter';
    }
}
