<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

/**
 * تَوان | Tavaan — Payment
 * WalletStoreInterface — قرارداد موجودی کیف پول داخلی (DOC-073 §2).
 *
 * اپلیکیشن پیاده‌سازی واقعی (جدول users/wallet) را به‌عنوان `payment.wallet`
 * در کانتینر bind می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
interface WalletStoreInterface
{
    /** موجودی جاری کاربر (minor unit). */
    public function balance(string $userId): int;

    /** کسر موجودی — false اگر نرسد (🔒 اتمیک در پیاده‌سازی DB). */
    public function deduct(string $userId, int $amount): bool;

    /** افزودن موجودی (refund). */
    public function credit(string $userId, int $amount): void;
}
