<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Search\SearchDriverInterface;
use Tavaan\Core\Contracts\Search\SearchManagerInterface;
use Tavaan\Engine\Search\Drivers\MeilisearchDriver;
use Tavaan\Engine\Search\Drivers\MySqlDriver;
use Tavaan\Engine\Search\Drivers\NativeDriver;
use Tavaan\Engine\Search\Drivers\PgSqlDriver;
use Tavaan\Engine\Search\Drivers\SqliteFtsDriver;
use Tavaan\Engine\Search\Drivers\TypesenseDriver;
use Tavaan\Engine\Search\Exceptions\SearchException;

/**
 * تَوان | Tavaan — Search
 * SearchManager — نقطهٔ ورود جست‌وجو (Facade: Search).
 *
 * درایورها از `config('search.indexes.{name}.driver')` یا پیش‌فرض
 * `config('search.default')` انتخاب می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SearchManager implements SearchManagerInterface
{
    /** @var array<string, SearchDriverInterface> name → driver (instance cache) */
    private array $drivers = [];

    /** @var array<string, \Closure> درایورهای سفارشی */
    private array $custom = [];

    /** @var array<string, mixed> پیکربندی ایستا (وقتی با array ساخته شود) */
    private array $staticConfig;

    private ?\Tavaan\Core\Contracts\Config\RepositoryInterface $configRepo = null;

    /**
     * @param array<string, mixed>|null $config تزریق مستقیم پیکربندی (تست)؛
     *                                          در غیر این صورت config('search') زنده خوانده می‌شود.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        ?array $config = null,
    ) {
        if ($config !== null) {
            $this->staticConfig = $config;
        } else {
            $this->staticConfig = [];
            if ($container->bound('config')) {
                $repo = $container->make('config');
                if ($repo instanceof \Tavaan\Core\Contracts\Config\RepositoryInterface) {
                    $this->configRepo = $repo;
                }
            }
        }
    }

    /**
     * پیکربندی زندهٔ جست‌وجو — تغییرات config پس از boot دیده می‌شود
     * (اما driverهای ساخته‌شده snapshot خود را نگه می‌دارند).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        if ($this->configRepo !== null) {
            return (array) $this->configRepo->get('search', []);
        }

        return $this->staticConfig;
    }

    /** دور انداختن نمونه‌های driver (پس از تغییر پیکربندی). */
    public function purgeDrivers(): void
    {
        $this->drivers = [];
    }

    // ─── درایور ──────────────────────────────────────────────────────────

    /**
     * درایور یک ایندکس (یا درایور پیش‌فرض وقتی name=null).
     */
    public function driver(?string $index = null): SearchDriverInterface
    {
        $driverName = $this->driverNameFor($index);
        if (isset($this->drivers[$driverName])) {
            return $this->drivers[$driverName];
        }

        $driver = $this->build($driverName);
        $this->drivers[$driverName] = $driver;

        return $driver;
    }

    public function extend(string $name, \Closure $factory): void
    {
        $this->custom[$name] = $factory;
        unset($this->drivers[$name]);
    }

    private function driverNameFor(?string $index): string
    {
        $config = $this->config();
        if ($index !== null) {
            $indexes = (array) ($config['indexes'] ?? []);
            $driver = $indexes[$index]['driver'] ?? null;
            if (\is_string($driver) && $driver !== '') {
                return $driver;
            }
        }

        return (string) ($config['default'] ?? 'native');
    }

    private function build(string $name): SearchDriverInterface
    {
        $config = $this->config();
        if (isset($this->custom[$name])) {
            $driver = ($this->custom[$name])($this->container, $config);
            if (!$driver instanceof SearchDriverInterface) {
                throw SearchException::unknownDriver($name);
            }

            return $driver;
        }

        return match ($name) {
            'native' => new NativeDriver($config),
            'sqlite' => new SqliteFtsDriver($this->connectionFor('sqlite'), $config),
            'mysql' => new MySqlDriver($this->connectionFor('mysql'), $config),
            'pgsql' => new PgSqlDriver($this->connectionFor('pgsql'), $config),
            'meilisearch' => new MeilisearchDriver((array) ($config['meilisearch'] ?? [])),
            'typesense' => new TypesenseDriver((array) ($config['typesense'] ?? [])),
            default => throw SearchException::unknownDriver($name),
        };
    }

    private function connectionFor(string $driver): ConnectionInterface
    {
        if (!$this->container->bound('db')) {
            throw SearchException::connectionRequired($driver);
        }
        /** @var \Tavaan\Engine\Database\ConnectionManager $manager */
        $manager = $this->container->make('db');

        $config = $this->config();
        $explicit = $config[$driver]['connection'] ?? null;
        if (\is_string($explicit) && $explicit !== '') {
            $connection = $manager->connection($explicit);
        } else {
            $connection = $manager->connection();
        }

        // بررسی سازگاری درایور اتصال
        $actual = '';
        try {
            if (\method_exists($connection, 'driver')) {
                $actual = (string) $connection->driver();
            }
        } catch (\Throwable) {
            $actual = '';
        }
        if ($actual !== '' && $actual !== $driver) {
            throw SearchException::connectionRequired($driver . ' (connection is ' . $actual . ')');
        }

        return $connection;
    }

    // ─── API عمومی ───────────────────────────────────────────────────────

    /**
     * ایندکس یک سند (Model یا array).
     *
     * @param object|array<string, mixed> $document
     */
    public function index(string $index, mixed $document, ?string $docId = null): void
    {
        [$id, $data] = $this->normalizeDocument($document, $docId);
        $this->driver($index)->index($index, $id, $data);
    }

    /**
     * ایندکس دسته‌ای.
     *
     * @param iterable<int|string, object|array<string, mixed>> $documents
     *
     * @return int تعداد ایندکس‌شده‌ها
     */
    public function indexMany(string $index, iterable $documents): int
    {
        $docs = [];
        foreach ($documents as $key => $document) {
            [$id, $data] = $this->normalizeDocument($document, \is_string($key) ? $key : null);
            $docs[$id] = $data;
        }
        if ($docs === []) {
            return 0;
        }
        $this->driver($index)->indexMany($index, $docs);

        return \count($docs);
    }

    /** حذف یک سند از ایندکس. */
    public function delete(string $index, mixed $idOrDocument): void
    {
        $docId = \is_object($idOrDocument) || \is_array($idOrDocument)
            ? $this->normalizeDocument($idOrDocument)[0]
            : (string) $idOrDocument;
        $this->driver($index)->delete($index, $docId);
    }

    /**
     * جست‌وجو.
     *
     * @param array<string, mixed> $options
     */
    public function search(string $index, string $query, array $options = []): SearchResult
    {
        return $this->driver($index)->search($index, $query, $options);
    }

    /** @return list<string> */
    public function suggest(string $index, string $query, int $limit = 5): array
    {
        return $this->driver($index)->suggest($index, $query, $limit);
    }

    public function createIndex(string $index): void
    {
        $this->driver($index)->createIndex($index);
    }

    public function dropIndex(string $index): void
    {
        $this->driver($index)->dropIndex($index);
    }

    public function indexExists(string $index): bool
    {
        return $this->driver($index)->indexExists($index);
    }

    public function flush(string $index): void
    {
        $this->driver($index)->flush($index);
    }

    public function count(string $index): int
    {
        return $this->driver($index)->count($index);
    }

    /**
     * بازسازی کامل یک ایندکس از Model (config indexes.{name}.model).
     *
     * @return int تعداد اسناد
     */
    public function reindex(string $index, int $chunk = 200): int
    {
        $indexes = (array) ($this->config()['indexes'] ?? []);
        $model = $indexes[$index]['model'] ?? null;
        if (!\is_string($model) || !\class_exists($model)) {
            throw SearchException::for('search.model_not_configured', ['index' => $index]);
        }

        $this->createIndex($index);
        $this->flush($index);

        $total = 0;
        $batch = [];
        /** @var iterable<int, object> $rows */
        $rows = \method_exists($model, 'cursor') ? $model::cursor() : $model::all();
        foreach ($rows as $row) {
            [$id, $data] = $this->normalizeDocument($row);
            $batch[$id] = $data;
            if (\count($batch) >= $chunk) {
                $this->driver($index)->indexMany($index, $batch);
                $total += \count($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->driver($index)->indexMany($index, $batch);
            $total += \count($batch);
        }

        return $total;
    }

    /**
     * تبدیل Model/array → (docId, data).
     *
     * @param object|array<string, mixed> $document
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function normalizeDocument(mixed $document, ?string $docId = null): array
    {
        if (\is_array($document)) {
            $data = $document;
            $id = $docId ?? (isset($data['id']) ? (string) $data['id'] : '');
            if ($id === '') {
                throw SearchException::for('search.missing_doc_id');
            }

            return [$id, $data];
        }

        if (!\is_object($document)) {
            throw SearchException::for('search.invalid_document');
        }

        $data = \method_exists($document, 'toSearchableArray')
            ? (array) $document->toSearchableArray()
            : (\method_exists($document, 'toArray') ? (array) $document->toArray() : \get_object_vars($document));

        if ($docId !== null) {
            return [$docId, $data];
        }

        if (\method_exists($document, 'searchId')) {
            $id = $document->searchId();
            if ($id !== null) {
                return [(string) $id, $data];
            }
        }
        if (\method_exists($document, 'getKey') && $document->getKey() !== null) {
            return [(string) $document->getKey(), $data];
        }
        if (isset($data['id'])) {
            return [(string) $data['id'], $data];
        }

        throw SearchException::for('search.missing_doc_id');
    }
}
