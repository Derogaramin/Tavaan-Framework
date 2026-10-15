<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * افزودن مصرف حافظه (KB).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class MemoryProcessor implements ProcessorInterface
{
    public function process(LogRecord $record): LogRecord
    {
        return $record->withExtra(['memory_kb' => (int) (\memory_get_usage(true) / 1024)]);
    }
}
