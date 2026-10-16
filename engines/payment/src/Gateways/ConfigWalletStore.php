<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

/**
 * تَوان | Tavaan — Payment
 * ConfigWalletStore — کیف پول پیش‌فرض بر اساس config (توسعه/تست).
 *
 * ⚠️ فقط برای محیط توسعه — در production پیاده‌سازی DB خود را
 * به‌عنوان `payment.wallet` ثبت کنید.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class ConfigWalletStore implements WalletStoreInterface
{
    /** @var array<string, int> userId → موجودی (minor unit) */
    private array $accounts;

    /**
     * @param array<string, int> $accounts
     */
    public function __construct(array $accounts = [])
    {
        $this->accounts = [];
        foreach ($accounts as $userId => $balance) {
            $this->accounts[(string) $userId] = (int) $balance;
        }
    }

    public function balance(string $userId): int
    {
        return $this->accounts[$userId] ?? 0;
    }

    public function deduct(string $userId, int $amount): bool
    {
        if ($amount < 0) {
            return false;
        }
        if (($this->accounts[$userId] ?? 0) < $amount) {
            return false;
        }

        $this->accounts[$userId] -= $amount;

        return true;
    }

    public function credit(string $userId, int $amount): void
    {
        if ($amount < 0) {
            return;
        }
        $this->accounts[$userId] = ($this->accounts[$userId] ?? 0) + $amount;
    }
}
