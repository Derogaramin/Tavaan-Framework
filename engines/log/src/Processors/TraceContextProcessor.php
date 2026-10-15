<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Context\TraceContext;
use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * افزودن trace_id/span_id/request_id به رکورد.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class TraceContextProcessor implements ProcessorInterface
{
    public function process(LogRecord $record): LogRecord
    {
        if ($record->traceId !== '') {
            return $record;
        }

        return $record->withTrace(TraceContext::traceId(), TraceContext::spanId());
    }
}
