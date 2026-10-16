<?php

declare(strict_types=1);

namespace Tavaan\Engine\Schedule\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Schedule
 * InvalidCronException — عبارت cron نامعتبر (DOC-062 §3).
 *
 * @api 1.0
 * @see docs/03-engines/schedule.md
 */
final class InvalidCronException extends TavaanException
{
    public function __construct(string $expression, string $reason = '')
    {
        parent::__construct(
            'schedule.invalid_cron',
            ['expr' => $expression, 'reason' => $reason],
            $reason !== '' ? $reason : 'عبارت cron باید ۵ فیلد معتبر داشته باشد.',
        );
    }
}
