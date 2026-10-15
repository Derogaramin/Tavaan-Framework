<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

/**
 * استثنایی دارای دادهٔ زمینهٔ قابل گزارش (Masking خودکار).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
interface ContextualException
{
    /** @return array<string, mixed> */
    public function reportContext(): array;
}
