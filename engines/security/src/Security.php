<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security;

use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Engine\Security\Normalize\InputNormalizer;
use Tavaan\Engine\Security\Sanitize\HtmlSanitizer;
use Tavaan\Engine\Security\Ssrf\SsrfGuard;

/**
 * تَوان | Tavaan — Security
 * Security — نقطهٔ ورود عمومی (Facade root — DOC-059 §14).
 *
 * sanitize/sign/validateSignature/normalize/ssrfCheck
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class Security
{
    /** @var array<string, HtmlSanitizer> کش پیکربندی‌های sanitizer */
    private array $sanitizers = [];

    /**
     * @param array<string, mixed> $config کلید security از config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?EncrypterInterface $crypt = null,
        private readonly ?SsrfGuard $ssrf = null,
        private readonly ?InputNormalizer $normalizer = null,
    ) {
    }

    /**
     * پاک‌سازی HTML با پیکربندی allowlist.
     *
     * @param string $configName rich_text|strict|plain یا config سفارشی
     * @param array<string, mixed> $override
     */
    public function sanitize(string $html, string $configName = 'rich_text', array $override = []): string
    {
        $key = $override === [] ? $configName : $configName . ':' . \md5((string) \json_encode($override));

        return ($this->sanitizers[$key] ??= $this->makeSanitizer($configName, $override))->sanitize($html);
    }

    /** @param array<string, mixed> $override */
    private function makeSanitizer(string $configName, array $override): HtmlSanitizer
    {
        $preset = (array) ($this->config['sanitizers'][$configName] ?? []);

        return new HtmlSanitizer($override !== [] ? \array_replace($preset, $override) : $preset);
    }

    /** نرمال‌سازی ورودی (bidi/zero-width/ارقام). */
    public function normalize(string $input): string
    {
        return ($this->normalizer ?? new InputNormalizer((array) ($this->config['normalize'] ?? [])))->normalize($input);
    }

    /** @param array<array-key, mixed> $input @return array<array-key, mixed> */
    public function normalizeArray(array $input): array
    {
        return ($this->normalizer ?? new InputNormalizer((array) ($this->config['normalize'] ?? [])))->normalizeArray($input);
    }

    /** بررسی SSRF — true/false (بدون exception). */
    public function ssrfCheck(string $url): bool
    {
        return $this->ssrfGuard()->isAllowed($url);
    }

    /** @throws \Tavaan\Engine\Security\Exceptions\SsrfException */
    public function assertSsrfAllowed(string $url): void
    {
        $this->ssrfGuard()->assertAllowed($url);
    }

    /** @return list<string> IPهای پین‌شده برای اتصال مستقیم */
    public function ssrfResolve(string $url): array
    {
        return $this->ssrfGuard()->resolvePinned($url);
    }

    private function ssrfGuard(): SsrfGuard
    {
        return $this->ssrf ?? new SsrfGuard((array) ($this->config['ssrf'] ?? []));
    }

    /**
     * امضای URL با انقضا (DOC-059 §2.6 — سازگار با signed URL روتر).
     */
    public function sign(string $url, ?\DateTimeInterface $expires = null): string
    {
        if ($this->crypt === null) {
            throw new \LogicException('Security::sign requires the crypt engine.');
        }

        $parts = \parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            \parse_str($parts['query'], $query);
        }
        unset($query['signature']);

        if ($expires !== null) {
            $query['expires'] = (string) $expires->getTimestamp();
        }
        \ksort($query);

        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '/');

        $payload = $base . ($query !== [] ? '?' . \http_build_query($query) : '');
        $signature = $this->crypt->hmac($payload, 'signed_url');

        $query['signature'] = $signature;

        return $base . '?' . \http_build_query($query);
    }

    /**
     * اعتبارسنجی امضای URL کامل.
     */
    public function validateSignature(string $url): bool
    {
        if ($this->crypt === null) {
            return false;
        }

        $parts = \parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            \parse_str($parts['query'], $query);
        }

        $signature = (string) ($query['signature'] ?? '');
        if ($signature === '') {
            return false;
        }
        unset($query['signature']);

        $expires = $query['expires'] ?? null;
        if ($expires !== null && (int) $expires < \Tavaan\Support\Time\Clock::timestamp()) {
            return false;
        }

        \ksort($query);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '/');
        $payload = $base . ($query !== [] ? '?' . \http_build_query($query) : '');

        return $this->crypt->verifyHmac($payload, $signature, 'signed_url');
    }
}
