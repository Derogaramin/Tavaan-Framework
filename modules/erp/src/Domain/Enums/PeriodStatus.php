<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PeriodStatus — وضعیت دورهٔ مالی (DOC-097 §4).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * @api 1.0
 */
enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function labelFa(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Closed => 'بسته',
        };
    }
}
