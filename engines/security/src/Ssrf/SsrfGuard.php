<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Ssrf;

use Tavaan\Engine\Http\Middleware\IpMatcher;
use Tavaan\Engine\Security\Exceptions\SsrfException;

/**
 * تَوان | Tavaan — Security
 * SsrfGuard — محافظ SSRF برای درخواست‌های خروجی (DOC-059 §2.5).
 *
 * 🔒 resolve DNS سپس بررسی **همهٔ** IPها (جلوگیری از DNS Rebinding).
 * 🔒 dnsResolve() یک‌بار resolve می‌کند و IP را برمی‌گرداند (DNS Pinning —
 *    http-client باید به همان IP وصل شود).
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class SsrfGuard
{
    /** CIDRهای مسدود (private/loopback/link-local/CGNAT) */
    private const BLOCKED_CIDRS = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        '169.254.0.0/16', '0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24',
        '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',   // TEST-NET
        '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
        '::1/128', 'fc00::/7', 'fe80::/10', '::/128', '::ffff:0:0/96',
    ];

    /**
     * @param array<string, mixed> $config security.ssrf
     * @param \Closure(string): list<string>|null $dnsResolver قابل تزریق برای تست
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?\Closure $dnsResolver = null,
    ) {
    }

    /**
     * بررسی URL — در صورت خطر → SsrfException.
     *
     * @throws SsrfException
     */
    public function assertAllowed(string $url): void
    {
        $this->check($url);
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->check($url);

            return true;
        } catch (SsrfException) {
            return false;
        }
    }

    /**
     * resolve + بررسی + بازگرداندن IP پین‌شده (برای اتصال مستقیم).
     *
     * @return list<string> IPهای امن
     *
     * @throws SsrfException
     */
    public function resolvePinned(string $url): array
    {
        $host = $this->check($url);

        if (\filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->resolve($host);
    }

    /**
     * @throws SsrfException
     */
    private function check(string $url): string
    {
        $parts = \parse_url(\trim($url));
        if ($parts === false) {
            throw new SsrfException('security.ssrf.invalid_url');
        }

        $scheme = \strtolower((string) ($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfException('security.ssrf.scheme', ['scheme' => $scheme]);
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '') {
            throw new SsrfException('security.ssrf.no_host');
        }

        // allowlist دامنه (webhookها)
        $allowedHosts = (array) ($this->config['allowed_hosts'] ?? []);
        if ($allowedHosts !== [] && $this->hostInList($host, $allowedHosts)) {
            return $host;
        }

        if ((bool) ($this->config['allow_private'] ?? false)) {
            return $host;
        }

        // host با userinfo (https://expected.com@evil.internal) → رد
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new SsrfException('security.ssrf.userinfo');
        }

        // 🔒 IPv6 داخل براکت ([::1]) → براکت حذف شود
        if (\str_starts_with($host, '[') && \str_ends_with($host, ']')) {
            $host = \substr($host, 1, -1);
        }

        // IP لیترال
        if (\filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isBlocked($host)) {
                throw new SsrfException('security.ssrf.private_ip', ['ip' => $host]);
            }

            return $host;
        }

        // 🔒 hostnameهای محلی شناخته‌شده (بدون DNS — defense in depth)
        $lowerHost = \strtolower(\rtrim($host, '.'));
        if ($lowerHost === 'localhost'
            || \str_ends_with($lowerHost, '.localhost')
            || \str_ends_with($lowerHost, '.local')
            || \str_ends_with($lowerHost, '.internal')
            || \str_ends_with($lowerHost, '.invalid')) {
            throw new SsrfException('security.ssrf.private_ip', ['host' => $host]);
        }

        // 🔒 دامنه: resolve و بررسی همهٔ IPها
        $ips = $this->resolve($host);
        if ($ips === []) {
            throw new SsrfException('security.ssrf.dns_failed', ['host' => $host]);
        }
        foreach ($ips as $ip) {
            if ($this->isBlocked($ip)) {
                throw new SsrfException('security.ssrf.private_ip', ['ip' => $ip, 'host' => $host]);
            }
        }

        return $host;
    }

    /** @return list<string> */
    public function resolve(string $host): array
    {
        if ($this->dnsResolver !== null) {
            $resolved = ($this->dnsResolver)($host);

            return \is_array($resolved) ? $resolved : [];
        }

        $ips = [];

        $v4 = \gethostbynamel($host);
        if (\is_array($v4)) {
            $ips = \array_merge($ips, $v4);
        }

        $records = @\dns_get_record($host, DNS_AAAA);
        if (\is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    public function isBlocked(string $ip): bool
    {
        if (\filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;   // نامعتبر → مسدود (fail-closed)
        }

        foreach (self::BLOCKED_CIDRS as $cidr) {
            if (IpMatcher::matches($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $hosts */
    private function hostInList(string $host, array $hosts): bool
    {
        $host = \strtolower($host);
        foreach ($hosts as $allowed) {
            $allowed = \strtolower((string) $allowed);
            if ($allowed === $host) {
                return true;
            }
            if (\str_starts_with($allowed, '*.') && \str_ends_with($host, \substr($allowed, 1))) {
                return true;
            }
        }

        return false;
    }
}
