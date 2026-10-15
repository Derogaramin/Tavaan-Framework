<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Filesystem;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * پر بودن دیسک هنگام نوشتن (حوزهٔ کد: FS).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class DiskFullException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'filesystem.disk_full', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($messageId, $context, 'فضای دیسک را آزاد کنید یا مسیر storage را منتقل نمایید.', null, 0, $previous);
    }
}
