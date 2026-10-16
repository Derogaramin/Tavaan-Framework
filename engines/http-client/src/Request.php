<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

/**
 * تَوان | Tavaan — HttpClient
 * Request — رکورد درخواست ارسال‌شده (برای fake/assertSent؛ DOC-064 §2).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $url,
        private readonly array $headers = [],
        private readonly ?string $body = null,
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function url(): string
    {
        return $this->url;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /** @return array<string, mixed>|null بدنهٔ JSON در صورت امکان */
    public function json(): ?array
    {
        if ($this->body === null) {
            return null;
        }
        $decoded = \json_decode($this->body, true);

        return \is_array($decoded) ? $decoded : null;
    }

    public function hasHeader(string $name, ?string $value = null): bool
    {
        $actual = $this->header($name);
        if ($actual === null) {
            return false;
        }

        return $value === null || $actual === $value;
    }

    /**
     * آیا URL با الگو (wildcard `*`) مطابقت دارد؟
     *
     * الگوهای بدون scheme (مثل `api.example.com/*`) با host+path هم سنجیده می‌شوند.
     */
    public function urlIs(string $pattern): bool
    {
        if (self::matchesPattern($this->url, $pattern)) {
            return true;
        }

        if (!\str_contains($pattern, '://')) {
            $parts = \parse_url($this->url);
            $hostPath = (string) ($parts['host'] ?? '') . (string) ($parts['path'] ?? '');

            return self::matchesPattern($hostPath, $pattern);
        }

        return false;
    }

    /** مطابقت الگوی wildcard (`api.example.com/*`) با URL. */
    public static function matchesPattern(string $url, string $pattern): bool
    {
        if ($pattern === $url || $pattern === '*') {
            return true;
        }

        $regex = '#^' . \str_replace('\*', '.*', \preg_quote($pattern, '#')) . '$#i';

        return \preg_match($regex, $url) === 1;
    }
}
