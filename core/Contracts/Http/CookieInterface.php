<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Http;

/**
 * قرارداد کوکی (DOC-042).
 *
 * @api 1.0
 * @see docs/03-engines/cookie.md
 */
interface CookieInterface
{
    public function name(): string;

    public function value(): string;

    /** @return array<string, mixed> قالب setcookie() */
    public function toArray(): array;
}
