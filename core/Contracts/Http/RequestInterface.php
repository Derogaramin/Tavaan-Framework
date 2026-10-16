<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد درخواست HTTP (DOC-014 §2.3) — Immutable.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface RequestInterface
{
    public function method(): string;

    public function uri(): UriInterface;

    public function path(): string;

    public function fullUrl(): string;

    public function isSecure(): bool;

    public function ip(): ?string;

    public function userAgent(): ?string;

    public function header(string $key, ?string $default = null): ?string;

    /** @return array<string, list<string>> */
    public function headers(): array;

    public function query(string $key, mixed $default = null): mixed;

    public function input(string $key, mixed $default = null): mixed;

    /** @return array<string, mixed> */
    public function all(): array;

    /** همهٔ کوکی‌های درخواست. @return array<string, mixed> */
    public function cookies(): array;

    public function file(string $key): ?UploadedFileInterface;

    /** @return array<string, UploadedFileInterface> */
    public function files(): array;

    public function cookie(string $key, mixed $default = null): mixed;

    public function json(?string $key = null, mixed $default = null): mixed;

    public function bearerToken(): ?string;

    public function expectsJson(): bool;

    public function is(string ...$patterns): bool;

    public function withAttribute(string $key, mixed $value): static;

    public function attribute(string $key, mixed $default = null): mixed;

    /** @param array<string, mixed> $query */
    public function withQuery(array $query): static;

    public function withMethod(string $method): static;
}
