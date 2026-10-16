<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Realtime
 * RealtimeException — خطای عمومی موتور بلادرنگ (DOC-045).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
class RealtimeException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }

    public static function unknownBroker(string $broker): static
    {
        return new static('realtime.unknown_broker', ['broker' => $broker], 'broker معتبر: array|database|redis');
    }

    public static function brokerUnavailable(string $broker, string $reason): static
    {
        return new static('realtime.broker_unavailable', ['broker' => $broker, 'reason' => $reason]);
    }

    public static function channelForbidden(string $channel): static
    {
        return new static('realtime.channel_forbidden', ['channel' => $channel]);
    }

    public static function socketsRequired(): static
    {
        return new static('realtime.sockets_required', [], 'برای WebSocket از درایور SSE (بدون ext) استفاده کنید یا ext-sockets/Swoole نصب کنید.');
    }
}
