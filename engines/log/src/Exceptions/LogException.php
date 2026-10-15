<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای نوشتن/چرخش لاگ.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class LogException extends TavaanException
{
    public static function writeFailed(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            messageId: 'log.write_failed',
            context: ['path' => $path, 'reason' => $reason],
            hint: 'مجوز نوشتن و فضای دیسک storage/logs را بررسی کنید.',
            previous: $previous,
        );
    }

    public static function channelNotFound(string $channel): self
    {
        return new self(
            messageId: 'log.channel_not_found',
            context: ['channel' => $channel],
            hint: 'کانال را در config log.php تعریف کنید.',
        );
    }
}
