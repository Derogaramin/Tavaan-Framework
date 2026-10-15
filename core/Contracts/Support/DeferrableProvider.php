<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Support;

/**
 * Provider تعویق‌پذیر: فقط وقتی سرویس‌هایش لازم شود register می‌شود.
 *
 * @api 1.0
 * @see docs/02-architecture/container-di.md
 */
interface DeferrableProvider
{
    /** @return list<string> abstractهایی که این Provider تأمین می‌کند */
    public function provides(): array;
}
