<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * StockMovementType — نوع حرکت موجودی (DOC-097 §2.2).
 */

namespace Modules\Erp\Domain\Enums;

/**
 * @api 1.0
 */
enum StockMovementType: string
{
    case Receipt = 'receipt';          // ورود به انبار (+)
    case Issue = 'issue';              // خروج از انبار (−)
    case TransferIn = 'transfer_in';   // انتقال ورود (+)
    case TransferOut = 'transfer_out'; // انتقال خروج (−)
    case Adjustment = 'adjustment';    // اصلاح (± — جهت از sign مقدار در سرویس)

    /** آیا این نوع موجودی را افزایش می‌دهد؟ */
    public function increases(): bool
    {
        return $this === self::Receipt || $this === self::TransferIn;
    }

    public function decreases(): bool
    {
        return $this === self::Issue || $this === self::TransferOut;
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Receipt => 'رسید',
            self::Issue => 'حواله',
            self::TransferIn => 'انتقال (ورود)',
            self::TransferOut => 'انتقال (خروج)',
            self::Adjustment => 'اصلاح',
        };
    }
}
