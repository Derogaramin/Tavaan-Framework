<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api\Resources;

/**
 * تَوان | Tavaan — Api
 * MissingValue — نشانگر «فیلد شرطی حذف‌شده» (خروجی when/whenLoaded).
 *
 * یک singleton بی‌محتوا؛ در resolve() کلیدهایی که مقدارشان این است حذف می‌شوند.
 * قابل پیمایش (خالی) است تا `...$this->when(false, …)` بی‌صدا ادغام شود.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class MissingValue implements \JsonSerializable, \IteratorAggregate
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function jsonSerialize(): mixed
    {
        return null;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([]);
    }
}
