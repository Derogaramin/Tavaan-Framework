<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * BomCosting — بهای مواد لازم برای تولید (DOC-097 §2.4).
 */

namespace Modules\Erp\Domain\Services;

/**
 * BomCosting — cost = Σ(qty_component × avg_cost_component) — int minor.
 * دامنهٔ خالص.
 *
 * @api 1.0
 */
final class BomCosting
{
    /**
     * @param list<array{item_id: string, qty: float}> $requirements مواد لازم (explode شده)
     * @param array<string, int> $avgCosts itemId → avg_cost (minor)
     * @param array<string, float> $availables itemId → موجودی قابل‌دسترس (کنترل)
     * @return array{total_cost: int, shortages: list<string>}
     */
    public function costOf(array $requirements, array $avgCosts, array $availables = []): array
    {
        $total = 0;
        $shortages = [];

        foreach ($requirements as $requirement) {
            $itemId = (string) ($requirement['item_id'] ?? '');
            $qty = (float) ($requirement['qty'] ?? 0);
            $unitCost = (int) ($avgCosts[$itemId] ?? 0);

            $total += (int) \round($qty * $unitCost);

            if ($availables !== [] && (float) ($availables[$itemId] ?? 0.0) + 0.0005 < $qty) {
                $shortages[] = $itemId;
            }
        }

        return ['total_cost' => $total, 'shortages' => $shortages];
    }
}
