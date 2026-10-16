<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Search
 * SearchException — خطای عمومی موتور جست‌وجو (DOC-052).
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
class SearchException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public static function for(string $messageId, array $context = [], ?string $hint = null): static
    {
        return new static($messageId, $context, $hint);
    }

    public static function unknownDriver(string $driver): static
    {
        return new static('search.unknown_driver', ['driver' => $driver], 'درایورهای معتبر: native|sqlite|mysql|pgsql|meilisearch|typesense');
    }

    public static function invalidIndexName(string $name): static
    {
        return new static('search.invalid_index_name', ['name' => $name], 'نام ایندکس فقط می‌تواند شامل حروف/عدد/_/- باشد (حداکثر ۶۴ کاراکتر).');
    }

    public static function driverUnavailable(string $driver, string $reason): static
    {
        return new static('search.driver_unavailable', ['driver' => $driver, 'reason' => $reason]);
    }

    public static function queryTooLong(int $max): static
    {
        return new static('search.query_too_long', ['max' => $max]);
    }

    public static function connectionRequired(string $driver): static
    {
        return new static('search.connection_required', ['driver' => $driver]);
    }

    public static function persistFailed(string $path, string $reason): static
    {
        return new static('search.persist_failed', ['path' => $path, 'reason' => $reason]);
    }

    public static function fieldNotAllowed(string $field, string $index): static
    {
        return new static('search.field_not_allowed', ['field' => $field, 'index' => $index], 'فیلد باید در فهرست filterable/sortable/searchable ایندکس باشد.');
    }
}
