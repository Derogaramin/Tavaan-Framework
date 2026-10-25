<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * VoucherLineBuilder — اشتراک listenerهای سند (کد حساب از config).
 */

namespace Modules\Erp\Infrastructure\Listeners;

use Tavaan\Core\Facade\Facades\Config;

/**
 * VoucherLineBuilder — نگاشت kind کالا → حساب موجودی + کدهای سیستمی.
 *
 * @internal مخصوص listenerهای ERP
 */
final class VoucherLineBuilder
{
    /** کد حساب موجودی بر اساس نوع کالا (merchandise/raw_material/finished). */
    public static function inventoryAccountFor(?string $kind): string
    {
        $accounts = Config::get('erp.accounting.accounts', []);

        return match ($kind) {
            'raw_material' => (string) ($accounts['raw_material'] ?? '1302'),
            'finished' => (string) ($accounts['finished_goods'] ?? '1304'),
            default => (string) ($accounts['inventory'] ?? '1301'),
        };
    }

    public static function account(string $key, string $fallback): string
    {
        $accounts = Config::get('erp.accounting.accounts', []);

        return (string) ($accounts[$key] ?? $fallback);
    }

    /** جمع خطوط رویداد موجودی per حساب موجودی (تجمیع برای ردیف‌های سند). @param list<array<string, mixed>> $lines @return array<string, int> account_code → amount */
    public static function sumByInventoryAccount(array $lines): array
    {
        $sums = [];
        foreach ($lines as $line) {
            $amount = (int) \round((float) ($line['qty'] ?? 0) * (int) ($line['unit_cost'] ?? 0));
            if ($amount <= 0) {
                continue;
            }
            $code = self::inventoryAccountFor(isset($line['kind']) ? (string) $line['kind'] : null);
            $sums[$code] = ($sums[$code] ?? 0) + $amount;
        }

        return $sums;
    }
}
