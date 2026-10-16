<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facades\Facade;

/**
 * Facade درهم‌سازی رمز (Hash).
 *
 * @method static string make(string $value)
 * @method static bool check(string $value, string $hash)
 * @method static bool needsRehash(string $hash)
 * @method static array info(string $hash)
 * @method static \Tavaan\Core\Contracts\Encryption\HasherInterface driver(?string $name = null)
 * @mixin \Tavaan\Engine\Hash\HashManager
 * @api 1.0
 * @see docs/03-engines/hash.md
 */
final class Hash extends Facade
{
    protected static function accessor(): string
    {
        return 'hash';
    }
}
