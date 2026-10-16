<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پرداخت (Payment — DOC-073).
 *
 * @method static \Tavaan\Core\Contracts\Payment\GatewayInterface gateway(?string $name = null)
 * @method static void extend(string $name, \Closure|string $factory)
 * @method static list<string> gateways()
 * @method static string defaultGateway()
 * @method static string defaultCurrency()
 * @method static \Tavaan\Engine\Payment\PurchaseResult purchase(\Tavaan\Engine\Payment\PurchaseRequest|array $request, ?string $gateway = null)
 * @method static \Tavaan\Engine\Payment\PaymentResult verify(string|\Tavaan\Engine\Payment\Transaction $authorityOrTx, ?string $gateway = null, array $callbackMeta = [])
 * @method static \Tavaan\Engine\Payment\CallbackOutcome handleCallback(array $query, ?string $gateway = null)
 * @method static \Tavaan\Engine\Payment\CallbackOutcome notify(string $gateway, string $body, array $headers = [])
 * @method static \Tavaan\Engine\Payment\RefundResult refund(\Tavaan\Engine\Payment\Transaction|string $transactionOrId, ?\Tavaan\Engine\Database\ORM\Support\Money $amount = null)
 * @method static \Tavaan\Engine\Payment\PaymentStatus inquire(string $authority, ?string $gateway = null)
 * @method static \Tavaan\Engine\Payment\Transaction|null findTransaction(string $id)
 * @method static \Tavaan\Engine\Payment\Transaction|null findByAuthority(string $authority, ?string $gateway = null)
 * @method static string resultUrl(\Tavaan\Engine\Payment\Transaction $transaction)
 * @mixin \Tavaan\Engine\Payment\PaymentManager
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class Payment extends Facade
{
    protected static function accessor(): string
    {
        return 'payment';
    }
}
