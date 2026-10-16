<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Batch;

use Tavaan\Engine\Queue\Exceptions\QueueException;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * PendingChain — زنجیرهٔ jobها با اجرای ترتیبی (DOC-061 §3).
 *
 * ```php
 * Queue::chain([new Step1(), new Step2(), new Step3()])->dispatch();
 * ```
 *
 * 🔒 هر عضو فقط پس از **موفقیت** عضو قبلی push می‌شود
 * (CallQueuedHandler::dispatchNextInChain)؛ شکست = توقف زنجیره.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class PendingChain
{
    /** @var list<object|string> */
    private array $jobs = [];

    private ?string $queue = null;

    private ?string $connection = null;

    /** @param iterable<object|string> $jobs */
    public function __construct(private readonly QueueManager $manager, iterable $jobs)
    {
        foreach ($jobs as $job) {
            $this->jobs[] = $job;
        }
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

    /**
     * dispatch عضو اول با `chain` = بقیهٔ اعضا (payload آماده).
     *
     * @return int|string|null شناسهٔ job اول
     */
    public function dispatch(): int|string|null
    {
        if ($this->jobs === []) {
            throw QueueException::for('queue.empty_chain', [], 'chain بدون job معنا ندارد.');
        }

        $serializer = $this->manager->serializer();
        $connection = $this->manager->connection($this->connection);

        $rest = $this->jobs;
        $first = \array_shift($rest);
        if ($first === null) {
            throw QueueException::for('queue.empty_chain', [], 'chain بدون job معنا ندارد.');
        }

        $chainPayloads = [];
        foreach ($rest as $member) {
            $chainPayloads[] = $serializer->createJobPayload($member, [], $this->resolveQueue($member));
        }

        $queue = $this->resolveQueue($first);
        $payload = $serializer->createJobPayload($first, [], $queue);

        // زنجیره در payload عضو اول درج می‌شود
        $decoded = \json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);
        $decoded['chain'] = $chainPayloads;
        $payload = \json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $connection->pushRaw($payload, $queue);
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
