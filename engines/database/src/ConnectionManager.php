<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Engine\Database\Connections\MySqlConnection;
use Tavaan\Engine\Database\Connections\PostgresConnection;
use Tavaan\Engine\Database\Connections\SqliteConnection;
use Tavaan\Engine\Database\Exceptions\ConnectionException;
use Tavaan\Engine\Database\Grammars\MySqlGrammar;
use Tavaan\Engine\Database\Grammars\PostgresGrammar;
use Tavaan\Engine\Database\Grammars\SqliteGrammar;

/**
 * تَوان | Tavaan — Database
 * ConnectionManager — مدیریت چند اتصال (default/tenant/reporting) با ساخت Lazy.
 *
 * ### مثال
 * ```php
 * DB::table('users')->get();                 // اتصال پیش‌فرض
 * DB::connection('reporting')->table(...);   // اتصال خاص
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    /** @var array<string, \Closure(string, array<string, mixed>): Connection> */
    private array $extensions = [];

    /**
     * @param LoggerInterface|(\Closure(): ?LoggerInterface)|null $logger
     *        Closure = resolving تنبل (تا LogManager زودهنگام — و پیش از تنظیم log.default — ساخته نشود)
     */
    public function __construct(
        private readonly ?RepositoryInterface $config = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly LoggerInterface|\Closure|null $logger = null,
    ) {
    }

    private function resolveLogger(): ?LoggerInterface
    {
        if ($this->logger instanceof \Closure) {
            $logger = ($this->logger)();

            return $logger instanceof LoggerInterface ? $logger : null;
        }

        return $this->logger;
    }

    /** اتصال پیش‌فرض. */
    public function connection(?string $name = null): Connection
    {
        $name ??= $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->makeConnection($name);
    }

    public function getDefaultConnection(): string
    {
        return (string) ($this->config?->get('database.default', 'sqlite') ?? 'sqlite');
    }

    public function setDefaultConnection(string $name): void
    {
        $this->config?->set('database.default', $name);
    }

    /** @return array<string, mixed> */
    public function configuration(?string $name = null): array
    {
        $name ??= $this->getDefaultConnection();

        /** @var array<string, mixed>|null $config */
        $config = $this->config?->get('database.connections.' . $name);
        if ($config === null) {
            throw new ConnectionException(
                context: ['connection' => $name],
                hint: 'اتصال در config/database.php → connections تعریف نشده است.',
            );
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private function makeConfiguration(string $name): array
    {
        $config = $this->configuration($name);

        // pagination/query_log از ریشهٔ database.* خوانده می‌شوند
        $config['pagination'] = (array) ($this->config?->get('database.pagination', []) ?? []);

        return $config;
    }

    private function makeConnection(string $name): Connection
    {
        $config = $this->makeConfiguration($name);
        $driver = (string) ($config['driver'] ?? '');

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($name, $config);
        }

        $grammar = $this->makeGrammar($driver, (string) ($config['prefix'] ?? ''), $config);
        $queryLog = $this->makeQueryLog($driver);

        return match ($driver) {
            'mysql', 'mariadb' => new MySqlConnection($name, $config, $grammar, new Processor(), $queryLog, $this->events),
            'pgsql', 'postgres' => new PostgresConnection($name, $config, $grammar, new Processor(), $queryLog, $this->events),
            'sqlite' => $this->makeSqlite($name, $config, $grammar, $queryLog),
            default => throw new ConnectionException(
                context: ['driver' => $driver],
                hint: 'درایورهای پشتیبانی‌شده: mysql، pgsql، sqlite.',
            ),
        };
    }

    /** @param array<string, mixed> $config */
    private function makeSqlite(string $name, array $config, MySqlGrammar|PostgresGrammar|SqliteGrammar $grammar, QueryLog $queryLog): SqliteConnection
    {
        $database = (string) ($config['database'] ?? ':memory:');
        if ($database !== ':memory:' && !\is_file($database)) {
            $dir = \dirname($database);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0775, true);
            }
            \touch($database);
        }

        return new SqliteConnection($name, $config, $grammar, new Processor(), $queryLog, $this->events);
    }

    /** @param array<string, mixed> $config */
    private function makeGrammar(string $driver, string $prefix, array $config): MySqlGrammar|PostgresGrammar|SqliteGrammar
    {
        return match ($driver) {
            'mysql', 'mariadb' => new MySqlGrammar($prefix),
            'pgsql', 'postgres' => new PostgresGrammar($prefix, (string) ($config['schema'] ?? 'public')),
            'sqlite' => new SqliteGrammar($prefix),
            default => throw new ConnectionException(context: ['driver' => $driver]),
        };
    }

    private function makeQueryLog(string $driver): QueryLog
    {
        $enabled = (bool) ($this->config?->get('database.query_log.enabled', false) ?? false);
        $slow = (float) ($this->config?->get('database.query_log.slow_threshold', 100) ?? 100);
        $max = (int) ($this->config?->get('database.query_log.max_entries', 500) ?? 500);
        $n1 = (bool) ($this->config?->get('database.n_plus_one_detection', false) ?? false);
        $n1Threshold = (int) ($this->config?->get('database.n_plus_one_threshold', 5) ?? 5);

        return new QueryLog($enabled, $slow, $max, $n1, $n1Threshold, $this->resolveLogger(), $this->events);
    }

    /**
     * ثبت درایور سفارشی.
     *
     * @param \Closure(string, array<string, mixed>): Connection $factory
     */
    public function extend(string $driver, \Closure $factory): void
    {
        $this->extensions[$driver] = $factory;
    }

    /** حذف اتصال ساخته‌شده (برای تست/tenant switch). */
    public function purge(?string $name = null): void
    {
        $name ??= $this->getDefaultConnection();
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /** @return array<string, Connection> */
    public function resolvedConnections(): array
    {
        return $this->connections;
    }

    // ─── forwarding به اتصال پیش‌فرض ──────────────────────────────────────

    public function table(string $table, ?string $as = null): QueryBuilder
    {
        return $this->connection()->query()->table($table, $as);
    }

    public function query(): QueryBuilder
    {
        return $this->connection()->query();
    }

    /** @param array<int|string, mixed> $bindings */
    public function raw(string $value, array $bindings = []): Expression
    {
        return new Expression($value, $bindings);
    }

    /** @param array<int|string, mixed> $bindings @return list<array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->connection()->select($sql, $bindings);
    }

    /** @param array<int|string, mixed> $bindings */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->connection()->statement($sql, $bindings);
    }

    /** @param \Closure(ConnectionManager): mixed $callback */
    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed
    {
        return $this->connection()->transaction($callback, $attempts, $isolation);
    }

    /**
     * forwarding عمومی به اتصال پیش‌فرض (DB::withCte و …).
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }
}
