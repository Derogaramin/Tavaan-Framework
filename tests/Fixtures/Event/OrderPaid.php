<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

/** Fixture: رویداد شیئی. */
final class OrderPaid implements PaidInterface
{
    public function __construct(public readonly string $orderId = '01J', public readonly int $amount = 1000)
    {
    }
}
