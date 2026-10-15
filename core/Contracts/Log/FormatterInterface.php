<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Log;

use Tavaan\Engine\Log\LogRecord;

/**
 * قرارداد Formatter (LogRecord → رشته).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
interface FormatterInterface
{
    public function format(LogRecord $record): string;
}
