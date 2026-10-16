<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment\Gateways;

use Tavaan\Engine\Database\ORM\Support\Money;
use Tavaan\Engine\Payment\PaymentResult;
use Tavaan\Engine\Payment\PaymentStatus;
use Tavaan\Engine\Payment\PurchaseRequest;
use Tavaan\Engine\Payment\PurchaseResult;
use Tavaan\Engine\Payment\RefundRequest;
use Tavaan\Engine\Payment\RefundResult;
use Tavaan\Engine\Payment\VerifyRequest;

/**
 * تَوان | Tavaan — Payment
 * WalletGateway — کیف پول داخلی (DOC-073 §2).
 *
 * موجودی از `WalletStoreInterface` خوانده می‌شود (binding: `payment.wallet`؛
 * پیش‌فرض ConfigWalletStore از config `payment.gateways.wallet.accounts`).
 * 🔒 کسر موجودی فقط در verify (نه purchase) — پرداخت تأییدنشده موجودی را کم نمی‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class WalletGateway extends AbstractHttpGateway
{
    public function __construct(
        array $config = [],
        ?\Tavaan\Engine\HttpClient\Factory $http = null,
        private readonly ?WalletStoreInterface $wallet = null,
    ) {
        parent::__construct($config, $http);
    }

    public function name(): string
    {
        return 'wallet';
    }

    protected function requiredConfig(): array
    {
        return [];   // بدون اعتبارنامهٔ خارجی
    }

    public function purchase(PurchaseRequest $request): PurchaseResult
    {
        $userId = (string) ($request->meta['user_id'] ?? '');
        if ($userId === '') {
            return PurchaseResult::failure('wallet.user_required');
        }

        $balance = $this->wallet()->balance($userId);
        if ($balance < $request->amount->amount) {
            return PurchaseResult::failure('wallet.insufficient_funds', ['balance' => $balance]);
        }

        $authority = 'wallet_' . \Tavaan\Support\Ulid::generate()->toString();

        // بازگشت فوری به callback (بدون redirect خارجی)
        return PurchaseResult::success(
            $authority,
            $request->callbackUrl . (\str_contains($request->callbackUrl, '?') ? '&' : '?')
                . 'Authority=' . \rawurlencode($authority) . '&Status=OK',
            ['user_id' => $userId, 'balance' => $balance],
        );
    }

    public function verify(VerifyRequest $request): PaymentResult
    {
        $userId = (string) ($request->meta['user_id'] ?? '');
        if ($userId === '') {
            return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, ['error' => 'wallet.user_required']);
        }

        // 🔒 کسر اتمیک در verify — اگر موجودی نرسد، ناموفق
        if (!$this->wallet()->deduct($userId, $request->amount->amount)) {
            return new PaymentResult(PaymentStatus::Failed, null, null, $request->amount, $request->authority, ['error' => 'wallet.insufficient_funds']);
        }

        return new PaymentResult(
            PaymentStatus::Success,
            $request->authority,   // ref = authority داخلی
            null,
            $request->amount,
            $request->authority,
            ['user_id' => $userId],
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $userId = (string) ($request->meta['user_id'] ?? '');
        if ($userId === '') {
            return RefundResult::fail('wallet.user_required');
        }

        $this->wallet()->credit($userId, $request->amount->amount);

        return RefundResult::ok($request->authority, ['user_id' => $userId]);
    }

    /** موجودی کاربر (برای نمایش/بررسی). */
    public function balanceOf(string $userId): int
    {
        return $this->wallet()->balance($userId);
    }

    private function wallet(): WalletStoreInterface
    {
        if ($this->wallet !== null) {
            return $this->wallet;
        }

        $container = \Tavaan\Engine\Payment\PaymentResolver::container();
        if ($container !== null && $container->bound('payment.wallet')) {
            $store = $container->make('payment.wallet');
            if ($store instanceof WalletStoreInterface) {
                return $store;
            }
        }

        return new ConfigWalletStore((array) ($this->config['accounts'] ?? []));
    }
}
