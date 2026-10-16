<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Database\Exceptions\ConnectionException;
use Tavaan\Engine\Database\Exceptions\DeadlockException;
use Tavaan\Engine\Database\Exceptions\QueryException;
use Tavaan\Engine\Database\Grammars\Grammar;

/**
 * تَوان | Tavaan — Database
 * Connection — پایهٔ اتصال PDO با Lazy connect، Reconnect خودکار،
 * Prepared Statement ۱۰۰٪، Query Log و Read/Write Splitting (DOC-047 §3/§6).
 *
 * 🔒 `ATTR_EMULATE_PREPARES = false` تا prepared واقعی سمت سرور باشد.
 * 🔒 bindingهای null با PARAM_NULL؛ DateTime → `Y-m-d H:i:s.u`.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
abstract class Connection implements ConnectionInterface
{
    private ?\PDO $pdo = null;

    private ?\PDO $readPdo = null;

    private bool $reconnected = false;

    private bool $recordsModified = false;

    private ?TransactionManager $transactions = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly string $connectionName,
        private readonly array $config,
        private readonly Grammar $grammar,
        private readonly Processor $processor,
        private readonly QueryLog $queryLog,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    // ─── هویت ─────────────────────────────────────────────────────────────

    public function name(): string
    {
        return $this->connectionName;
    }

    /** @return array<string, mixed> */
    public function config(?string $key = null): array
    {
        if ($key === null) {
            return $this->config;
        }

        return (array) ($this->config[$key] ?? []);
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function grammar(): Grammar
    {
        return $this->grammar;
    }

    public function processor(): Processor
    {
        return $this->processor;
    }

    public function queryLog(): array
    {
        return $this->queryLog->toArray();
    }

    public function queryLogInstance(): QueryLog
    {
        return $this->queryLog;
    }

    public function maxPerPage(): int
    {
        return (int) ($this->config['pagination']['max_per_page'] ?? 100);
    }

    // ─── PDO (lazy + reconnect) ───────────────────────────────────────────

    public function pdo(): \PDO
    {
        return $this->pdo ??= $this->createPdo($this->writeDsnConfig());
    }

    public function readPdo(): \PDO
    {
        $sticky = (bool) ($this->config['sticky'] ?? true);
        if ($sticky && $this->recordsModified) {
            return $this->pdo();
        }

        return $this->readPdo ??= $this->createPdo($this->readDsnConfig());
    }

    public function hasReadReplica(): bool
    {
        $hosts = (array) ($this->config['read']['host'] ?? []);

        return \array_filter($hosts, static fn ($h): bool => \is_string($h) && $h !== '') !== [];
    }

    /** @return array<string, mixed> */
    private function writeDsnConfig(): array
    {
        $config = $this->config;
        $writeHost = (string) ($this->config['write']['host'] ?? '');
        if ($writeHost !== '') {
            $config['host'] = $writeHost;
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private function readDsnConfig(): array
    {
        $config = $this->config;
        $hosts = \array_values(\array_filter(
            (array) ($this->config['read']['host'] ?? []),
            static fn ($h): bool => \is_string($h) && $h !== '',
        ));
        if ($hosts !== []) {
            $config['host'] = $hosts[\random_int(0, \count($hosts) - 1)];
        }

        return $config;
    }

    /** @param array<string, mixed> $config */
    private function createPdo(array $config): \PDO
    {
        $dsn = $this->grammar->dsn($config);

        $options = (array) ($config['options'] ?? []);
        // 🔒 prepared واقعی + exception mode — حتی اگر config فراموش کند
        $options[\PDO::ATTR_ERRMODE] ??= \PDO::ERRMODE_EXCEPTION;
        $options[\PDO::ATTR_EMULATE_PREPARES] = false;

        try {
            $pdo = new \PDO(
                $dsn,
                isset($config['username']) ? (string) $config['username'] : null,
                isset($config['password']) ? (string) $config['password'] : null,
                $options,
            );
        } catch (\PDOException $e) {
            throw new ConnectionException(
                context: [
                    'driver'   => $this->driver(),
                    'host'     => $config['host'] ?? null,
                    'database' => $this->safeDatabaseName($config),
                ],
                previous: $e,
            );
        }

        $this->configure($pdo, $config);

        return $pdo;
    }

    /** نام database در SQLite یک «مسیر» است → در context نشت نکند. */
    private function safeDatabaseName(array $config): mixed
    {
        return $this->driver() === 'sqlite' ? '(sqlite)' : ($config['database'] ?? null);
    }

    /** @param array<string, mixed> $config */
    protected function configure(\PDO $pdo, array $config): void
    {
        // hook درایورها
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->readPdo = null;
        $this->reconnected = false;
    }

    // ─── اجرای کوئری ──────────────────────────────────────────────────────

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = [], bool $useWrite = false): array
    {
        return $this->run($sql, $bindings, function (\PDO $pdo, string $sql, array $bindings): array {
            $stmt = $this->prepareAndBind($pdo, $sql, $bindings);
            $stmt->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->processor->processSelect($rows);
        }, $useWrite);
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = [], bool $useWrite = false): ?array
    {
        $rows = $this->run($sql, $bindings, function (\PDO $pdo, string $sql, array $bindings): array {
            $stmt = $this->prepareAndBind($pdo, $sql, $bindings);
            $stmt->execute();

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row === false ? [] : [$row];
        }, $useWrite);

        return $rows[0] ?? null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function cursor(string $sql, array $bindings = [], bool $useWrite = false): \Generator
    {
        $pdo = $useWrite || !$this->hasReadReplica() ? $this->pdo() : $this->readPdo();
        $start = \microtime(true);

        try {
            $stmt = $this->prepareAndBind($pdo, $sql, $bindings);
            $stmt->execute();

            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        } catch (\PDOException $e) {
            throw $this->mapException($e, $sql, $bindings);
        }

        $this->logQuery($sql, $bindings, (\microtime(true) - $start) * 1000);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function insert(string $sql, array $bindings = [], bool $useWrite = true): bool
    {
        return $this->statement($sql, $bindings, $useWrite);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function update(string $sql, array $bindings = [], bool $useWrite = true): int
    {
        return $this->affectingStatement($sql, $bindings, $useWrite);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function delete(string $sql, array $bindings = [], bool $useWrite = true): int
    {
        return $this->affectingStatement($sql, $bindings, $useWrite);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = [], bool $useWrite = true): bool
    {
        $result = $this->run($sql, $bindings, static function (\PDO $pdo, string $sql, array $bindings): bool {
            $stmt = self::bindStatement($pdo, $sql, $bindings);

            return $stmt->execute();
        }, $useWrite);

        if ($useWrite) {
            $this->recordsModified = true;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = [], bool $useWrite = true): int
    {
        $count = $this->run($sql, $bindings, static function (\PDO $pdo, string $sql, array $bindings): int {
            $stmt = self::bindStatement($pdo, $sql, $bindings);
            $stmt->execute();

            return $stmt->rowCount();
        }, $useWrite);

        if ($useWrite) {
            $this->recordsModified = true;
        }

        return $count;
    }

    public function lastInsertId(?string $name = null): string|int
    {
        $id = $this->pdo()->lastInsertId($name);

        return \ctype_digit($id) ? (int) $id : $id;
    }

    /**
     * اجرای عمومی با Reconnect خودکار («server has gone away») و logging.
     *
     * @param array<int|string, mixed>                              $bindings
     * @param \Closure(\PDO, string, array<int|string, mixed>): mixed $callback
     */
    protected function run(string $sql, array $bindings, \Closure $callback, bool $useWrite = false): mixed
    {
        $start = \microtime(true);

        try {
            $pdo = $useWrite || !$this->hasReadReplica() ? $this->pdo() : $this->readPdo();
            $result = $callback($pdo, $sql, $bindings);
        } catch (\PDOException $e) {
            if ($this->causedByLostConnection($e) && !$this->reconnected) {
                $this->reconnected = true;
                $this->pdo = null;
                $this->readPdo = null;
                $this->events?->dispatch('database.reconnected', ['connection' => $this->connectionName]);

                $pdo = $useWrite || !$this->hasReadReplica() ? $this->pdo() : $this->readPdo();
                $result = $callback($pdo, $sql, $bindings);
            } else {
                $this->logQuery($sql, $bindings, (\microtime(true) - $start) * 1000);

                throw $this->mapException($e, $sql, $bindings);
            }
        }

        $this->logQuery($sql, $bindings, (\microtime(true) - $start) * 1000);

        return $result;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function prepareAndBind(\PDO $pdo, string $sql, array $bindings): \PDOStatement
    {
        return self::bindStatement($pdo, $sql, $bindings);
    }

    /**
     * 🔒 تنها نقطهٔ bind — همیشه prepare؛ تایپ صریح هر binding.
     *
     * @param array<int|string, mixed> $bindings
     */
    private static function bindStatement(\PDO $pdo, string $sql, array $bindings): \PDOStatement
    {
        $stmt = $pdo->prepare($sql);

        $i = 1;
        foreach ($bindings as $key => $value) {
            $param = \is_int($key) ? $i++ : $key;
            $type = match (true) {
                \is_int($value)  => \PDO::PARAM_INT,
                \is_bool($value) => \PDO::PARAM_BOOL,
                $value === null  => \PDO::PARAM_NULL,
                $value instanceof \DateTimeInterface => \PDO::PARAM_STR,
                default          => \PDO::PARAM_STR,
            };

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s.u');
            }

            $stmt->bindValue($param, $value, $type);
        }

        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function logQuery(string $sql, array $bindings, float $timeMs): void
    {
        $this->queryLog->record($sql, $bindings, $timeMs, $this->connectionName);
    }

    // ─── تراکنش ───────────────────────────────────────────────────────────

    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed
    {
        return $this->transactionManager()->transaction($callback, $attempts, $isolation);
    }

    public function beginTransaction(?string $isolation = null): void
    {
        $this->transactionManager()->begin($isolation);
    }

    public function commit(): void
    {
        $this->transactionManager()->commit();
    }

    public function rollBack(int $toLevel = 0): void
    {
        $this->transactionManager()->rollBack($toLevel);
    }

    public function transactionLevel(): int
    {
        return $this->transactions?->level() ?? 0;
    }

    public function transactionManager(): TransactionManager
    {
        return $this->transactions ??= new TransactionManager($this, $this->events);
    }

    public function setIsolationLevel(string $level): void
    {
        $allowed = ['read uncommitted', 'read committed', 'repeatable read', 'serializable'];
        $normalized = \strtolower(\trim($level));
        if (!\in_array($normalized, $allowed, true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown isolation level [%s].', $level));
        }

        if ($this->driver() === 'sqlite') {
            return;   // SQLite فقط serializable
        }

        $this->pdo()->exec($this->driver() === 'pgsql'
            ? "SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL " . \strtoupper($normalized)
            : 'SET SESSION TRANSACTION ISOLATION LEVEL ' . \strtoupper($normalized));
    }

    // ─── Builder ──────────────────────────────────────────────────────────

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function table(string $table, ?string $as = null): QueryBuilderInterface
    {
        return $this->query()->table($table, $as);
    }

    /** @param array<int|string, mixed> $bindings */
    public function raw(string $value, array $bindings = []): Expression
    {
        return new Expression($value, $bindings);
    }

    // ─── نگاشت استثنا ─────────────────────────────────────────────────────

    /**
     * @param array<int|string, mixed> $bindings
     */
    protected function mapException(\PDOException $e, string $sql, array $bindings): QueryException
    {
        $state = (string) $e->getCode();

        if ($this->causedByDeadlock($e)) {
            return new DeadlockException($sql, $bindings, $state, 'تراکنش به بن‌بست خورد؛ با retry خودکار قابل رفع است.', $e);
        }

        return new QueryException($sql, $bindings, $state, null, $e);
    }

    private function causedByLostConnection(\PDOException $e): bool
    {
        $message = \strtolower($e->getMessage());

        return \in_array((string) $e->getCode(), ['HY000', '2006', '2013'], true)
            && (\str_contains($message, 'gone away')
                || \str_contains($message, 'lost connection')
                || \str_contains($message, 'broken pipe')
                || \str_contains($message, 'connection reset'));
    }

    private function causedByDeadlock(\PDOException $e): bool
    {
        $message = \strtolower($e->getMessage());

        return \in_array((string) $e->getCode(), ['40001', '40P01'], true)
            || \str_contains($message, 'deadlock');
    }
}
