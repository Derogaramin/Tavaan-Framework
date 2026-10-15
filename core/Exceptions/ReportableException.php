<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

/**
 * استثنا تصمیم می‌گیرد گزارش شود یا خیر.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
interface ReportableException
{
    public function shouldReport(): bool;
}
