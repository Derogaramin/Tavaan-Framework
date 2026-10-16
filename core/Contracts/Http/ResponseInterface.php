<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

use Tavaan\Engine\Http\Cookie\Cookie;

/**
 * قرارداد پاسخ HTTP (DOC-014 §2.3) — Immutable.
 *
 * ⚠️ send() بدون exit است؛ Kernel الگوی sendAndTerminate را اجرا می‌کند
 * (DOC-039 §4.2) تا terminate() همیشه فرصت اجرا داشته باشد.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
interface ResponseInterface
{
    public function statusCode(): int;

    public function withStatus(int $code, string $reason = ''): static;

    /** خواندن یک header. */
    public function header(string $key, ?string $default = null): ?string;

    public function hasHeader(string $key): bool;

    /** Immutable: افزودن/جایگزینی header. */
    public function withHeader(string $key, string $value, bool $replace = true): static;

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): static;

    public function body(): string;

    public function withBody(string $body): static;

    /** @param int $flags JSON encode flags */
    public function withJson(mixed $data, int $status = 200, int $flags = 0): static;

    public function cookie(Cookie $cookie): static;

    /** ارسال headers + body (بدون exit). */
    public function send(): void;

    public function isSuccessful(): bool;

    public function isRedirect(): bool;
}
