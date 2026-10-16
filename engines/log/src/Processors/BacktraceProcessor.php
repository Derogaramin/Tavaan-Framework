<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\Log\Logger;
use Tavaan\Engine\Log\LogManager;
use Tavaan\Engine\Log\LogRecord;

/**
 * یافتن فایل/خط فراخوانندهٔ واقعی (با حذف فریم‌های فریمورک).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class BacktraceProcessor implements ProcessorInterface
{
    /** @var list<string> کلاس‌هایی که فریم‌هایشان رد می‌شود */
    private const SKIP_PREFIXES = [
        'Tavaan\\Engine\\Log\\',
        'Tavaan\\Core\\Facade\\',
    ];

    public function process(LogRecord $record): LogRecord
    {
        if ($record->file !== null) {
            return $record;
        }

        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if ($class === '') {
                continue;
            }
            if ($this->isSkipped($class)) {
                continue;
            }

            return $record->withSource($frame['file'] ?? null, $frame['line'] ?? null);
        }

        return $record;
    }

    private function isSkipped(string $class): bool
    {
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (\str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return \in_array($class, [Facade::class, Logger::class, LogManager::class], true);
    }
}
