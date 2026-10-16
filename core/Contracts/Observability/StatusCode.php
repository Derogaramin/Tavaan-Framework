<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Observability;

/**
 * تَوان | Tavaan — Contracts
 * StatusCode — وضعیت Span (هم‌تراز OTLP: UNSET=0, OK=1, ERROR=2).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
enum StatusCode: int
{
    case Unset = 0;
    case Ok = 1;
    case Error = 2;
}
