<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * افزودن زمان اجرا از TVN_START (میلی‌ثانیه).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class RuntimeProcessor implements ProcessorInterface
{
    public function process(LogRecord $record): LogRecord
    {
        $start = \defined('TVN_START') ? (float) \TVN_START : \microtime(true);

        return $record->withExtra(['runtime_ms' => \round((\microtime(true) - $start) * 1000, 3)]);
    }
}
