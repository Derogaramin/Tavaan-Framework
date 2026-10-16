<?php

declare(strict_types=1);

namespace Tavaan\Engine\Auth\Jwt;

/**
 * تَوان | Tavaan — Auth
 * Base64Url — کدگذاری base64url (RFC 4648 §5 — بدون padding).
 *
 * @api 1.0
 * @see docs/03-engines/auth.md
 */
final class Base64Url
{
    private function __construct()
    {
    }

    public static function encode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        $decoded = \base64_decode(\strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64url payload.');
        }

        return $decoded;
    }
}
