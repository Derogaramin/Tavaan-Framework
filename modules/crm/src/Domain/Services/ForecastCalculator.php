<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ForecastCalculator — forecast وزنی و نرخ برد/باخت (DOC-096 §3.1/§6).
 */

namespace Modules\Crm\Domain\Services;

/**
 * ForecastCalculator — تصمیم خالص روی aggregateهای عددی:
 *
 * - weighted = Σ value × probability٪ (int — بدون float، ADR-0013-هم‌خانواده)
 * - win rate = won / (won + lost)
 *
 * @api 1.0
 */
final class ForecastCalculator
{
    /**
     * @param list<array{value: int, probability: int}> $opportunities
     */
    public function weightedTotal(array $opportunities): int
    {
        $total = 0;
        foreach ($opportunities as $opp) {
            $value = (int) ($opp['value'] ?? 0);
            $probability = \max(0, \min(100, (int) ($opp['probability'] ?? 0)));
            $total += (int) \floor($value * $probability / 100);
        }

        return $total;
    }

    /** @return array{won: int, lost: int, open: int, win_rate: float, open_value: int} */
    public function winLoss(int $wonCount, int $lostCount, int $openCount = 0, int $openValue = 0): array
    {
        $closed = $wonCount + $lostCount;

        return [
            'won' => $wonCount,
            'lost' => $lostCount,
            'open' => $openCount,
            'win_rate' => $closed > 0 ? \round($wonCount * 100 / $closed, 1) : 0.0,
            'open_value' => $openValue,
        ];
    }

    /**
     * تجمیع pipeline بر اساس stage.
     *
     * @param list<array{stage: string, value: int, probability: int}> $rows
     *
     * @return array<string, array{count: int, value: int, weighted: int}>
     */
    public function byStage(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? '');
            if ($stage === '') {
                continue;
            }
            $out[$stage] ??= ['count' => 0, 'value' => 0, 'weighted' => 0];
            $out[$stage]['count']++;
            $out[$stage]['value'] += (int) ($row['value'] ?? 0);
            $out[$stage]['weighted'] += (int) \floor((int) ($row['value'] ?? 0) * \max(0, \min(100, (int) ($row['probability'] ?? 0))) / 100);
        }

        return $out;
    }
}
