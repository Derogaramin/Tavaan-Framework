<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Queue
 * QueueException — خطای عمومی موتور صف (DOC-061).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
class QueueException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }

    public static function unknownDriver(string $driver): static
    {
        return new static('queue.unknown_driver', ['driver' => $driver], 'درایور صف را در config/queue.php تعریف کنید.');
    }

    public static function connectionNotConfigured(string $name): static
    {
        return new static('queue.connection_not_configured', ['connection' => $name]);
    }

    public static function unserializable(string $reason): static
    {
        return new static('queue.unserializable', ['reason' => $reason], 'Closure/Resource قابل صف‌رفتن نیستند؛ از Job شیء‌محور استفاده کنید.');
    }
}
