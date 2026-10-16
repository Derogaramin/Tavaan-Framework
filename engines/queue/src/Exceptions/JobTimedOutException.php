<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Queue
 * JobTimedOutException — اجرای job از timeout گذشت (DOC-061 §4.2).
 *
 * با pcntl_alarm (POSIX) در میانهٔ اجرا پرتاب می‌شود؛ بدون pcntl
 * بررسی پس‌از‌اجرا (cooperative) انجام می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobTimedOutException extends TavaanException
{
    public function __construct(string $jobName = '', int $timeout = 0)
    {
        parent::__construct(
            'queue.job_timed_out',
            ['job' => $jobName, 'timeout' => $timeout],
            'timeout job را در property $timeout یا --timeout worker تنظیم کنید.',
        );
    }
}
