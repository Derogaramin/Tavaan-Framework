<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Formatters;

use Tavaan\Core\Contracts\Log\FormatterInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * تَوان | Tavaan — Log
 * LineFormatter — قالب خطی خوانا (توسعه).
 *
 * الگو: `[2026-10-15 12:34:56] production.ERROR: message.id {"ctx":…} [trace:…] [user:…]`
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class LineFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $context = $record->context === []
            ? ''
            : ' ' . (string) \json_encode($record->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $trace = $record->traceId === '' ? '' : ' [trace:' . \substr($record->traceId, 0, 8) . ']';
        $user = $record->userId === null ? '' : ' [user:' . $record->userId . ']';

        return \sprintf(
            "[%s] %s.%s: %s%s%s%s\n",
            $record->timestamp === '' ? '' : \substr($record->timestamp, 0, 19),
            $record->environment,
            \strtoupper($record->level->value),
            $record->message,
            $context,
            $trace,
            $user,
        );
    }
}
