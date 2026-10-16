<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Health;

/**
 * تَوان | Tavaan — Observability
 * Up — نشانگر سالم‌بودن یک بررسی سلامتی.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Up
{
    public function __construct(public readonly string $message = '')
    {
    }
}
