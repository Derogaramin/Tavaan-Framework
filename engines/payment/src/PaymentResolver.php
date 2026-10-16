<?php

declare(strict_types=1);

namespace Tavaan\Engine\Payment;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Payment
 * PaymentResolver — دسترسی ایستا (اختیاری) به Container.
 *
 * @api 1.0
 * @see docs/03-engines/payment.md
 */
final class PaymentResolver
{
    private static ?ContainerInterface $container = null;

    private function __construct()
    {
    }

    public static function set(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function container(): ?ContainerInterface
    {
        return self::$container;
    }
}
