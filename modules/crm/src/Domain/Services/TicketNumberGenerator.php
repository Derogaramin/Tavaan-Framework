<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketNumberGenerator — شمارهٔ تیکت (TKT-YYYYMMDD-XXXX).
 */

namespace Modules\Crm\Domain\Services;

use Tavaan\Support\Time\Clock;

/**
 * TicketNumberGenerator — قالب: `<prefix>-<yyyymmdd>-<5 hex>` —
 * یکتایی با exists-check در UseCase تضمین و retry می‌شود (الگوی shop).
 *
 * @api 1.0
 */
final class TicketNumberGenerator
{
    public function __construct(private readonly string $prefix = 'TKT')
    {
    }

    public function generate(): string
    {
        $date = Clock::utcNow()->format('Ymd');

        return $this->prefix . '-' . $date . '-' . \strtoupper(\substr(\bin2hex(\random_bytes(3)), 0, 5));
    }
}
