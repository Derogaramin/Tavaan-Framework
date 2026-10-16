<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Engine\HttpClient\Exceptions\RequestException;

/**
 * تَوان | Tavaan — HttpClient
 * Response — پاسخ HTTP دریافتی (Immutable؛ DOC-064 §2).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class Response
{
    /**
     * @param array<string, string> $headers کلیدها lowercase
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly string $effectiveUrl = '',
    ) {
    }

    /** ساخت پاسخ (برای fake stubs). */
    public static function make(string|array $body = '', int $status = 200, array $headers = []): self
    {
        if (\is_array($body)) {
            $body = (string) \json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
            $headers = \array_change_key_case($headers) + ['content-type' => 'application/json'];
        }

        return new self($body, $status, \array_change_key_case($headers));
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function effectiveUrl(): string
    {
        return $this->effectiveUrl;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * بدنهٔ JSON — با dot-path اختیاری (`json('data.id')`).
     */
    public function json(?string $path = null, mixed $default = null): mixed
    {
        $decoded = \json_decode($this->body, true);
        if (!\is_array($decoded)) {
            return $default;
        }

        if ($path === null) {
            return $decoded;
        }

        $value = $decoded;
        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, string> کوکی‌های Set-Cookie (ساده: name=value) */
    public function cookies(): array
    {
        $out = [];
        foreach ($this->headers as $name => $value) {
            if ($name !== 'set-cookie') {
                continue;
            }
            foreach (\explode(',', $value) as $cookie) {
                $pair = \explode(';', $cookie, 2)[0];
                if (\str_contains($pair, '=')) {
                    [$cname, $cvalue] = \explode('=', \trim($pair), 2);
                    $out[\trim($cname)] = \trim($cvalue);
                }
            }
        }

        return $out;
    }

    /** 🔴 اگر 4xx/5xx → RequestException. */
    public function throw(): self
    {
        if ($this->failed()) {
            throw RequestException::for($this, $this->effectiveUrl);
        }

        return $this;
    }

    /** @param callable(self): bool $condition */
    public function throwIf(callable $condition): self
    {
        if ($condition($this)) {
            throw RequestException::for($this, $this->effectiveUrl);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
