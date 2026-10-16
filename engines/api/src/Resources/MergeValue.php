<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Resources;

/**
 * تَوان | Tavaan — Api
 * MergeValue — نشانگر «این آرایه را در والد ادغام کن» (خروجی mergeWhen).
 *
 * قابل پیمایش است تا سبک `...$this->mergeWhen(…)` در toArray هم کار کند.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class MergeValue implements \IteratorAggregate
{
    /** @param array<array-key, mixed> $data */
    public function __construct(public readonly array $data)
    {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }
}
