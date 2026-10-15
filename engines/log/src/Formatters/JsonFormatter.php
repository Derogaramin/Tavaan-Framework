<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Formatters;

use Tavaan\Core\Contracts\Log\FormatterInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * تَوان | Tavaan — Log
 * JsonFormatter — JSON Lines ساختاریافته (پیش‌فرض تولید).
 *
 * 🔒 فارسی بدون escape (JSON_UNESCAPED_UNICODE).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class JsonFormatter implements FormatterInterface
{
    private const FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function format(LogRecord $record): string
    {
        return (string) \json_encode($record->toArray(), self::FLAGS) . "\n";
    }
}
