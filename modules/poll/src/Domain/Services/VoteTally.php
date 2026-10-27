<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * VoteTally — جمع‌بندی خالص نتایج (بدون DB/HTTP).
 */

namespace Modules\Poll\Domain\Services;

/**
 * VoteTally — شمارش + درصد هر گزینه.
 *
 * دامنهٔ خالص: ورودی فهرست گزینه‌ها و نقشهٔ شمارش‌ها، خروجی ردیف‌های نتیجه.
 * درصد با دقت یک رقم اعشار؛ جمع درصدها ممکن است به‌دلیل گردکردن ۱۰۰ نشود
 * (رفتار استاندارد نظرسنجی).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class VoteTally
{
    /**
     * @param list<string>          $options        برچسب گزینه‌ها (به‌ترتیب اندیس)
     * @param array<int, int>       $countsByOption شمارش هر اندیس گزینه
     *
     * @return list<array{index: int, label: string, count: int, percent: float}>
     */
    public function tally(array $options, array $countsByOption): array
    {
        $total = 0;
        foreach ($options as $index => $label) {
            $total += \max(0, (int) ($countsByOption[$index] ?? 0));
        }

        $rows = [];
        foreach ($options as $index => $label) {
            $count = \max(0, (int) ($countsByOption[$index] ?? 0));
            $rows[] = [
                'index' => $index,
                'label' => $label,
                'count' => $count,
                'percent' => $total > 0 ? \round($count * 100 / $total, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /** مجموع کل رأی‌ها از نقشهٔ شمارش‌ها. */
    public function total(array $countsByOption): int
    {
        $total = 0;
        foreach ($countsByOption as $count) {
            $total += \max(0, (int) $count);
        }

        return $total;
    }

    /** گزینهٔ پیشتاز (بیشترین رأی) — در تساوی نخستین اندیس. */
    public function leader(array $tallyRows): ?int
    {
        $leader = null;
        $max = -1;
        foreach ($tallyRows as $row) {
            if ($row['count'] > $max) {
                $max = $row['count'];
                $leader = $row['index'];
            }
        }

        return $max > 0 ? $leader : null;
    }
}
