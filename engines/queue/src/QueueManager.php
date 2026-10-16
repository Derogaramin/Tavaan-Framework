<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Queue\BatchRepositoryInterface;
use Tavaan\Core\Contracts\Queue\FailedJobProviderInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Core\Contracts\Queue\QueueInterface;
use Tavaan\Engine\Queue\Batch\ArrayBatchRepository;
use Tavaan\Engine\Queue\Batch\Batch;
use Tavaan\Engine\Queue\Batch\DatabaseBatchRepository;
use Tavaan\Engine\Queue\Batch\PendingBatch;
use Tavaan\Engine\Queue\Batch\PendingChain;
use Tavaan\Engine\Queue\Exceptions\QueueException;
use Tavaan\Engine\Queue\Failed\DatabaseFailedJobProvider;
use Tavaan\Engine\Queue\Failed\NullFailedJobProvider;
use Tavaan\Engine\Queue\Queues\DatabaseQueue;
use Tavaan\Engine\Queue\Queues\FileQueue;
use Tavaan\Engine\Queue\Queues\NullQueue;
use Tavaan\Engine\Queue\Queues\RedisQueue;
use Tavaan\Engine\Queue\Queues\SyncQueue;

/**
 * تَوان | Tavaan — Queue
 * QueueManager — مدیر اتصالات صف: sync/database/redis/file/null (DOC-061 §2/§8).
 *
 * نقطهٔ ورود Facade (`Queue::…`) — متدهای push/later/pop/size به اتصال
 * پیش‌فرض واگذار می‌شوند. Batch/Chain/Worker از همین‌جا ساخته می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueManager implements QueueInterface
{
    /** @var array<string, QueueInterface> */
    private array $connections = [];

    /** @var array<string, \Closure(array<string, mixed>, ?ContainerInterface): QueueInterface> */
    private array $extensions = [];

    private ?Serializer $serializer = null;

    private ?FailedJobProviderInterface $failedProvider = null;

    private ?BatchRepositoryInterface $batchRepository = null;

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        // 🔒 وابستگی‌های تنبل: db/cache/events فقط در صورت نیاز resolve شوند
        private readonly ?ConnectionInterface $db = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?CacheRepository $cache = null,
    ) {
    }

    // ─── اتصالات ──────────────────────────────────────────────────────────

    public function getDefaultConnection(): string
    {
        return (string) ($this->config?->get('queue.default', 'database') ?? 'database');
    }

    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->resolveConnection($name);
    }

    /** ثبت درایور سفارشی. */
    public function extend(string $driver, \Closure $factory): void
    {
        $this->extensions[$driver] = $factory;
    }

    private function resolveConnection(string $name): QueueInterface
    {
        $config = (array) ($this->config?->get('queue.connections.' . $name, []) ?? []);
        if ($config === []) {
            throw QueueException::connectionNotConfigured($name);
        }

        $driver = (string) ($config['driver'] ?? $name);

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($config, $this->container);
        }

        $serializer = $this->serializer();

        return match ($driver) {
            'sync' => new SyncQueue($name, $config, $serializer, $this->container, $this->events, $this->failedProvider()),
            'database' => new DatabaseQueue($name, $config, $serializer, $this->requireDb($name), $this->container),
            'file' => new FileQueue($name, $config, $serializer, $this->container),
            'redis' => new RedisQueue($name, $config, $serializer, $this->container),
            'null' => new NullQueue($name, $config, $serializer, $this->container),
            default => throw QueueException::unknownDriver($driver),
        };
    }

    private function requireDb(string $connection): ConnectionInterface
    {
        if ($this->db !== null) {
            return $this->db;
        }

        $fromContainer = $this->container !== null && $this->container->bound(ConnectionInterface::class)
            ? $this->container->make(ConnectionInterface::class)
            : null;

        if (!$fromContainer instanceof ConnectionInterface) {
            throw QueueException::for(
                'queue.database_required',
                ['connection' => $connection],
                'اتصال database صف به موتور database نیاز دارد (migrationها را اجرا کنید).',
            );
        }

        return $fromContainer;
    }

    // ─── QueueInterface (اتصال پیش‌فرض) ───────────────────────────────────

    public function push(object|string $job, array $data = [], ?string $queue = null): int|string|null
    {
        return $this->connection()->push($job, $data, $queue);
    }

    public function pushOn(string $queue, object|string $job, array $data = []): int|string|null
    {
        return $this->connection()->pushOn($queue, $job, $data);
    }

    public function later(\DateTimeInterface|int $delay, object|string $job, array $data = [], ?string $queue = null): int|string|null
    {
        return $this->connection()->later($delay, $job, $data, $queue);
    }

    public function laterOn(string $queue, \DateTimeInterface|int $delay, object|string $job, array $data = []): int|string|null
    {
        return $this->connection()->laterOn($queue, $delay, $job, $data);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        return $this->connection()->pop($queue);
    }

    public function size(?string $queue = null): int
    {
        return $this->connection()->size($queue);
    }

    public function getConnectionName(): ?string
    {
        return $this->getDefaultConnection();
    }

    // ─── Batch / Chain ────────────────────────────────────────────────────

    /** @param iterable<object|string> $jobs */
    public function batch(iterable $jobs): PendingBatch
    {
        return new PendingBatch($this, $jobs);
    }

    /** @param iterable<object|string> $jobs */
    public function chain(iterable $jobs): PendingChain
    {
        return new PendingChain($this, $jobs);
    }

    public function findBatch(string $id): ?Batch
    {
        $record = $this->batchRepository()->find($id);

        return $record === null ? null : new Batch($record);
    }

    /**
     * اجرای callbackهای finally و ثبت پایان batch (توسط CallQueuedHandler).
     */
    public function finalizeBatch(string $id): void
    {
        $repository = $this->batchRepository();
        $record = $repository->find($id);
        if ($record === null) {
            return;
        }

        $batch = new Batch($record);
        $callbacks = $record['options']['finally'] ?? [];

        if (\is_array($callbacks)) {
            foreach ($callbacks as $callback) {
                $this->callFinallyCallback((string) $callback, $batch, $repository);
            }
        }

        $repository->finish($id);
    }

    private function callFinallyCallback(string $callback, Batch $batch, BatchRepositoryInterface $repository): void
    {
        if ($callback === '') {
            return;
        }

        // Closure ثبت‌شده (فقط مخزن array/تست)
        if (\str_starts_with($callback, 'closure:') && $repository instanceof ArrayBatchRepository) {
            $closure = $repository->closure($callback);
            if ($closure !== null && $this->container !== null) {
                $this->container->call($closure, ['batch' => $batch]);
            }

            return;
        }

        if ($this->container === null) {
            return;
        }

        // `Class@method` یا کلاس invokable
        $this->container->call($callback, ['batch' => $batch]);
    }

    // ─── Worker ───────────────────────────────────────────────────────────

    /**
     * اجرای worker (DOC-061 §3: `Queue::worker([...])`).
     *
     * @param array<string, mixed>|WorkerOptions $options
     *
     * @return int کد خروج
     */
    public function worker(array|WorkerOptions $options = []): int
    {
        $options = $options instanceof WorkerOptions ? $options : WorkerOptions::fromArray($options);

        $worker = $this->container !== null && $this->container->bound(Worker::class)
            ? $this->container->make(Worker::class)
            : new Worker($this, $this->container, $this->events, $this->cache);

        return $worker->run($options);
    }

    // ─── اجزای مشترک ──────────────────────────────────────────────────────

    public function serializer(): Serializer
    {
        return $this->serializer ??= new Serializer($this->container, $this->allowlistConfig());
    }

    public function failedProvider(): FailedJobProviderInterface
    {
        return $this->failedProvider ??= $this->resolveFailedProvider();
    }

    public function batchRepository(): BatchRepositoryInterface
    {
        return $this->batchRepository ??= $this->resolveBatchRepository();
    }

    public function container(): ContainerInterface
    {
        return $this->container
            ?? throw QueueException::for('queue.no_container', [], 'QueueManager بدون Container ساخته شده است.');
    }

    /** خواندن مقدار config موتور صف (مثل `failed.dlq`). */
    public function configValue(string $key, mixed $default = null): mixed
    {
        return $this->config?->get('queue.' . $key, $default) ?? $default;
    }

    /** @return array{prefixes: list<string>, classes: list<string>} */
    private function allowlistConfig(): array
    {
        $allowlist = (array) ($this->config?->get('queue.allowlist', []) ?? []);

        return [
            'prefixes' => \array_values(\array_map(strval(...), (array) ($allowlist['prefixes'] ?? ['App\\', 'Tavaan\\', 'Modules\\', 'Kits\\', 'Plugins\\']))),
            'classes' => \array_values(\array_map(strval(...), (array) ($allowlist['classes'] ?? []))),
        ];
    }

    private function resolveFailedProvider(): FailedJobProviderInterface
    {
        $driver = (string) ($this->config?->get('queue.failed.driver', 'database') ?? 'database');

        return match ($driver) {
            'database' => $this->db !== null
                ? new DatabaseFailedJobProvider($this->db, (string) ($this->config?->get('queue.failed.table', 'tvn_failed_jobs') ?? 'tvn_failed_jobs'))
                : new NullFailedJobProvider(),
            'null' => new NullFailedJobProvider(),
            default => new NullFailedJobProvider(),
        };
    }

    private function resolveBatchRepository(): BatchRepositoryInterface
    {
        $driver = (string) ($this->config?->get('queue.batching.driver', 'database') ?? 'database');

        return match ($driver) {
            'database' => $this->db !== null
                ? new DatabaseBatchRepository($this->db, (string) ($this->config?->get('queue.batching.table', 'tvn_job_batches') ?? 'tvn_job_batches'))
                : new ArrayBatchRepository(),
            'array' => new ArrayBatchRepository(),
            default => new ArrayBatchRepository(),
        };
    }
}
