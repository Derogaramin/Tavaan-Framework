<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

/**
 * تَوان | Tavaan — Testing
 * MockProxy — قرارداد Proxyهای تولیدشده توسط Mock (بدون eval — DOC-084E §4.1).
 *
 * هر Mock ساخته‌شده با `Mock::of(...)->getMock()` این قرارداد را پیاده می‌کند
 * و از طریق آن می‌توان Handler را برای بازرسی فراخوانی‌ها به دست آورد.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
interface MockProxy
{
    public function tvnMockHandler(): MockHandler;
}
