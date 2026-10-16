<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\WebSocket;

use Tavaan\Engine\Realtime\Exceptions\RealtimeException;

/**
 * تَوان | Tavaan — Realtime
 * WebSocketProtocolException — خطای پروتکل (فریم نامعتبر).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class WebSocketProtocolException extends RealtimeException
{
    public static function invalidFrame(string $reason): static
    {
        return self::for('realtime.invalid_frame', ['reason' => $reason]);
    }
}
