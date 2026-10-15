<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

/**
 * تَوان | Tavaan — Compat
 * RoundingMode — حالت‌های گردکردن Decimal.
 *
 * @api 1.0
 * @see docs/adr/ADR-0013-money-representation.md
 */
enum RoundingMode: string
{
    case HalfUp   = 'half_up';
    case HalfDown = 'half_down';
    case HalfEven = 'half_even';
    case Up       = 'up';
    case Down     = 'down';
}
