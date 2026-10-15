<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

/**
 * خطای موقت/قابل تلاش مجدد (Deadlock، Timeout، Lock).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
interface RetryableException
{
    /** ثانیه‌های پیشنهادی قبل از تلاش مجدد؛ null = بدون تأخیر */
    public function retryAfter(): ?int;

    public function maxRetries(): int;
}
