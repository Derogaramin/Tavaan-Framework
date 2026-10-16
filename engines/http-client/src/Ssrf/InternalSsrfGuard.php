<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Ssrf;

use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;

/**
 * تَوان | Tavaan — HttpClient
 * InternalSsrfGuard — محافظ SSRF درون‌ساز (DOC-064 §7).
 *
 * وقتی موتور security فعال نباشد، این guard جای `SsrfGuard` را می‌گیرد:
 * - فقط schemeهای http/https
 * - رد IPهای خصوصی/loopback/link-local/metadata (169.254.169.254)
 * - allowlist دامنه (`allowed_hosts`)
 * - 🔒 resolve DNS و بررسی همهٔ IPهای بازگشتی (جلوگیری از DNS rebinding سطح ۱)
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class InternalSsrfGuard implements SsrfCheckerInterface
{
    /**
     * @param array<string, mixed>          $config      {allowed_hosts, allow_private, max_port, allowed_ports}
     * @param \Closure(string): list<string>|null $dnsResolver قابل تزریق برای تست
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?\Closure $dnsResolver = null,
    ) {
    }

    public function assertAllowed(string $url): void
    {
        $this->check($url);
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->check($url);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @throws HttpClientException */
    private function check(string $url): string
    {
        $parts = \parse_url(\trim($url));
        if ($parts === false || !isset($parts['host']) || $parts['host'] === '') {
            throw new HttpClientException('http_client.invalid_url', ['url' => $url]);
        }

        $scheme = \strtolower((string) ($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new HttpClientException('http_client.ssrf_blocked', ['url' => $url, 'reason' => 'scheme ' . $scheme]);
        }

        $host = \strtolower($parts['host']);

        // allowlist دامنه
        $allowedHosts = (array) ($this->config['allowed_hosts'] ?? []);
        if ($allowedHosts !== [] && $this->hostInList($host, $allowedHosts)) {
            return $host;
        }

        // حالت dev: بدون محدودیت (پیش از port check — allow_private یعنی همه‌چیز مجاز)
        if ((bool) ($this->config['allow_private'] ?? false)) {
            return $host;
        }

        // 🔒 portهای غیرمعمول (SSH/DB/…) بسته شوند
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $allowedPorts = (array) ($this->config['allowed_ports'] ?? [80, 443, 8080, 8443]);
        if ($allowedPorts !== [] && !\in_array($port, \array_map(intval(...), $allowedPorts), true)) {
            throw new HttpClientException('http_client.ssrf_blocked', ['url' => $url, 'reason' => 'port ' . $port]);
        }

        // IP مستقیم
        if (\filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($host, $url);

            return $host;
        }

        // hostname → resolve و بررسی همهٔ IPها
        foreach ($this->resolve($host) as $ip) {
            $this->assertPublicIp($ip, $url);
        }

        return $host;
    }

    /** @throws HttpClientException */
    private function assertPublicIp(string $ip, string $url): void
    {
        $isPublic = \filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        // IPv6 loopback/ULA/link-local — filter_var پوشش کامل نمی‌دهد
        if ($isPublic && \str_contains($ip, ':')) {
            $lower = \strtolower($ip);
            $isPublic = $lower !== '::1'
                && !\str_starts_with($lower, 'fe8')   // link-local fe80::/10
                && !\str_starts_with($lower, 'fc')    // ULA fc00::/7
                && !\str_starts_with($lower, 'fd');
        }

        if (!$isPublic) {
            throw new HttpClientException('http_client.ssrf_blocked', [
                'url' => $url,
                'reason' => 'private/reserved IP: ' . $ip,
            ]);
        }
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->dnsResolver !== null) {
            return ($this->dnsResolver)($host);
        }

        $ips = [];
        $v4 = \gethostbynamel($host);
        if (\is_array($v4)) {
            $ips = \array_merge($ips, $v4);
        }
        if (\function_exists('dns_get_record')) {
            foreach (\dns_get_record($host, \DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        // 🔒 resolve ناموفق → رد (fail-closed)
        if ($ips === []) {
            throw new HttpClientException('http_client.dns_failed', ['host' => $host]);
        }

        return $ips;
    }

    /** @param list<string> $list */
    private function hostInList(string $host, array $list): bool
    {
        foreach ($list as $pattern) {
            $pattern = \strtolower((string) $pattern);
            if ($pattern === $host) {
                return true;
            }
            if (\str_starts_with($pattern, '*.') && \str_ends_with($host, \substr($pattern, 1))) {
                return true;
            }
        }

        return false;
    }
}
