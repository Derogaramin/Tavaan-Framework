<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report\Tests\Fixtures;

/**
 * فیکسچرهای تست موتور Report (DOC-072 §10).
 */

/** وضعیت سفارش — برای تست options: Enum::cases() (DOC-072 §3). */
enum ROrderStatus: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Canceled = 'canceled';
}
