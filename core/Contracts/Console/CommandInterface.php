<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Console;

/**
 * قرارداد یک Command در CLI تَوان.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
interface CommandInterface
{
    /** الگوی امضا: `name {arg?} {--opt=}` */
    public function signature(): string;

    public function description(): string;

    /** @return int کد خروج (0=SUCCESS) */
    public function handle(): int;
}
