<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms;

/**
 * تَوان | Tavaan — Notification
 * DeliveryStatus — وضعیت تحویل پیامک (DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
