<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/**
 * Fixture — job با ویژگی دلخواه برای آزمون‌های محافظ بازگشت Serializer:
 * ارجاع دوری، گراف لوزی (بدون دور)، عمق زیاد و زنجیرهٔ دوری.
 */
final class CycleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public mixed $ref = null)
    {
    }

    public function handle(): void
    {
    }
}
