<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Batch;

use Tavaan\Engine\Queue\Exceptions\QueueException;
use Tavaan\Engine\Queue\QueueManager;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Queue
 * PendingBatch — builder فلونت ساخت و dispatch یک batch (DOC-061 §3).
 *
 * ```php
 * $batch = Queue::batch([new Job1(), new Job2()])
 *     ->name('import')
 *     ->allowFailures()
 *     ->finally(CleanupBatch::class)
 *     ->dispatch();
 * ```
 *
 * 🔒 callbackهای finally باید **قابل سریال‌سازی** باشند
 * (کلاس invokable یا `Class@method`)؛ Closure فقط با مخزن array (تست).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class PendingBatch
{
    /** @var list<object|string> */
    private array $jobs = [];

    private string $name = '';

    private bool $allowFailures = false;

    /** @var list<string|\Closure> */
    private array $finallyCallbacks = [];

    private ?string $queue = null;

    private ?string $connection = null;

    private string $currentBatchId = '';

    /** @param iterable<object|string> $jobs */
    public function __construct(private readonly QueueManager $manager, iterable $jobs)
    {
        foreach ($jobs as $job) {
            $this->jobs[] = $job;
        }
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** شکست برخی jobها batch را لغو نکند. */
    public function allowFailures(bool $allow = true): self
    {
        $this->allowFailures = $allow;

        return $this;
    }

    /**
     * callback پایان batch (پس از اتمام همهٔ jobها).
     *
     * @param string|callable|list<string|callable> $callback کلاس invokable، `Class@method` یا Closure (فقط مخزن array)
     */
    public function finally(string|callable|array $callback): self
    {
        foreach (\is_array($callback) ? $callback : [$callback] as $item) {
            if (\is_array($item)) {
                $item = \implode('@', \array_map(strval(...), $item));
            }
            if ($item instanceof \Closure) {
                $this->finallyCallbacks[] = $item;
                continue;
            }
            if (\is_string($item) && $item !== '') {
                $this->finallyCallbacks[] = $item;
                continue;
            }
            throw QueueException::unserializable('finally callback باید string/callable باشد');
        }

        return $this;
    }

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /** ثبت batch و push همهٔ jobها با batchId مشترک. */
    public function dispatch(): Batch
    {
        if ($this->jobs === []) {
            throw QueueException::for('queue.empty_batch', [], 'batch بدون job معنا ندارد.');
        }

        $id = (string) Ulid::generate();
        $this->currentBatchId = $id;

        $options = [
            'name' => $this->name,
            'allowFailures' => $this->allowFailures,
            'finally' => $this->serializableFinally(),
        ];

        $this->manager->batchRepository()->create($id, \count($this->jobs), $options);

        $connection = $this->manager->connection($this->connection);

        foreach ($this->jobs as $job) {
            $connection->pushRaw($this->buildPayload($job), $this->resolveQueue($job));
        }

        return $this->manager->findBatch($id)
            ?? new Batch(['id' => $id, 'name' => $this->name, 'total_jobs' => \count($this->jobs)]);
    }

    /**
     * تبدیل callbackها به شکل ماندگار.
     *
     * @return list<string>
     */
    private function serializableFinally(): array
    {
        $repository = $this->manager->batchRepository();
        $out = [];

        foreach ($this->finallyCallbacks as $callback) {
            if (!$callback instanceof \Closure) {
                $out[] = (string) $callback;
                continue;
            }

            if (!$repository instanceof ArrayBatchRepository) {
                throw QueueException::unserializable('Closure در batch finally (مخزن ماندگار)');
            }

            $token = 'closure:' . (string) Ulid::generate();
            $repository->registerClosure($token, $callback);
            $out[] = $token;
        }

        return $out;
    }

    private function buildPayload(object|string $job): string
    {
        $queue = $this->resolveQueue($job);
        $payload = $this->manager->serializer()->createJobPayload($job, [], $queue);

        // batchId در payload درج می‌شود (worker با آن batch را به‌روز می‌کند)
        $decoded = \json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);
        $decoded['batchId'] = $this->currentBatchId;

        return \json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function resolveQueue(object|string $job): string
    {
        if ($this->queue !== null && $this->queue !== '') {
            return $this->queue;
        }
        if (\is_object($job) && \property_exists($job, 'queue')) {
            $name = $job->queue;
            if (\is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'default';
    }
}
