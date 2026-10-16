<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Health;

/**
 * تَوان | Tavaan — Observability
 * Down — نشانگر خرابی یک بررسی سلامتی (با پیام اختیاری).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Down
{
    public function __construct(public readonly string $message = '')
    {
    }
}
