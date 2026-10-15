<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Log;

use Tavaan\Engine\Log\LogRecord;

/**
 * قرارداد Processor (تغییر/افزودن داده به LogRecord قبل از نوشتن).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
interface ProcessorInterface
{
    public function process(LogRecord $record): LogRecord;
}
