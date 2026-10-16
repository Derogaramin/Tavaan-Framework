<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade امنیت عرضی (Security).
 *
 * @method static string sanitize(string $html, string $config = 'rich_text', array $override = [])
 * @method static string sign(string $url, ?\DateTimeInterface $expires = null)
 * @method static bool validateSignature(string $url)
 * @method static string normalize(string $input)
 * @method static array normalizeArray(array $input)
 * @method static bool ssrfCheck(string $url)
 * @method static void assertSsrfAllowed(string $url)
 * @method static array ssrfResolve(string $url)
 * @mixin \Tavaan\Engine\Security\Security
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class Security extends Facade
{
    protected static function accessor(): string
    {
        return 'security';
    }
}
