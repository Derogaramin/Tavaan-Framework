<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Middleware;

/**
 * تَوان | Tavaan — Http
 * IpMatcher — تطبیق IP با CIDR (IPv4 و IPv6 خالص با inet_pton).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class IpMatcher
{
    private function __construct()
    {
    }

    public static function matches(string $ip, string $cidr): bool
    {
        if ($cidr === '*') {
            return true;
        }
        if ($cidr === $ip) {
            return true;
        }
        if (!\str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = \explode('/', $cidr, 2);
        $ipBin = \inet_pton($ip);
        $subnetBin = \inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || \strlen($ipBin) !== \strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = \intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && \substr($ipBin, 0, $fullBytes) !== \substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (\ord($ipBin[$fullBytes]) & $mask) === (\ord($subnetBin[$fullBytes]) & $mask);
    }
}
