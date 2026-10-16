<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade جست‌وجو (Search — DOC-052).
 *
 * @method static void index(string $index, mixed $document, ?string $docId = null)
 * @method static int indexMany(string $index, iterable $documents)
 * @method static void delete(string $index, mixed $idOrDocument)
 * @method static \Tavaan\Engine\Search\SearchResult search(string $index, string $query, array $options = [])
 * @method static list<string> suggest(string $index, string $query, int $limit = 5)
 * @method static void createIndex(string $index)
 * @method static void dropIndex(string $index)
 * @method static bool indexExists(string $index)
 * @method static void flush(string $index)
 * @method static int count(string $index)
 * @method static int reindex(string $index, int $chunk = 200)
 * @method static \Tavaan\Core\Contracts\Search\SearchDriverInterface driver(?string $index = null)
 * @method static void extend(string $name, \Closure $factory)
 * @mixin \Tavaan\Engine\Search\SearchManager
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class Search extends Facade
{
    protected static function accessor(): string
    {
        return 'search';
    }
}
