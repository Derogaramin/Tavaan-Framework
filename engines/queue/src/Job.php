<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Exceptions\QueueException;

/**
 * تَوان | Tavaan — Queue
 * Job — پایهٔ jobهای برداشته‌شده از صف (DOC-061 §4).
 *
 * fire() → wrapper داخلی payload (CallQueuedHandler/CallQueuedEvent)
 * از طریق Container resolve و فراخوانی می‌شود (DI کامل در handle).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
abstract class Job implements JobInterface
{
    protected bool $deleted = false;

    protected bool $released = false;

    protected bool $failed = false;

    /** @var array<string, mixed>|null */
    protected ?array $parsedPayload = null;

    public function __construct(
        protected readonly ?ContainerInterface $container,
        protected readonly string $connectionName,
        protected readonly string $queueName,
        protected readonly string $rawPayload,
    ) {
    }

    public function getRawPayload(): string
    {
        return $this->rawPayload;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        if ($this->parsedPayload === null) {
            $decoded = \json_decode($this->rawPayload, true, flags: \JSON_THROW_ON_ERROR);
            $this->parsedPayload = \is_array($decoded) ? $decoded : [];
        }

        return $this->parsedPayload;
    }

    public function fire(): void
    {
        $container = $this->container
            ?? throw QueueException::for('queue.no_container', [], 'Job بدون Container قابل اجرا نیست.');

        $payload = $this->payload();
        $wrapper = (string) ($payload['job'] ?? Jobs\CallQueuedHandler::class);

        /** @var object $handler */
        $handler = $container->make($wrapper);
        $container->call([$handler, 'handle'], [
            'job' => $this,
            'data' => $payload['data'] ?? [],
        ]);
    }

    public function maxTries(): ?int
    {
        return $this->intOrNull($this->payload()['maxTries'] ?? null);
    }

    public function timeout(): ?int
    {
        return $this->intOrNull($this->payload()['timeout'] ?? null);
    }

    public function backoff(): int|array|null
    {
        $backoff = $this->payload()['backoff'] ?? null;

        if (\is_int($backoff)) {
            return $backoff;
        }
        if (\is_array($backoff) && $backoff !== []) {
            /** @var list<int> $list */
            $list = \array_values(\array_map(intval(...), $backoff));

            return $list;
        }

        return null;
    }

    public function retryUntil(): ?\DateTimeInterface
    {
        $ts = $this->intOrNull($this->payload()['retryUntil'] ?? null);

        return $ts === null ? null : new \DateTimeImmutable('@' . $ts);
    }

    public function uuid(): ?string
    {
        $uuid = $this->payload()['uuid'] ?? null;

        return \is_string($uuid) ? $uuid : null;
    }

    public function getName(): string
    {
        $name = $this->payload()['displayName'] ?? '';

        return \is_string($name) && $name !== '' ? $name : static::class;
    }

    public function getQueue(): string
    {
        return $this->queueName;
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /** @return array{key: string, max: int, seconds: int}|null */
    public function rateLimit(): ?array
    {
        $limit = $this->payload()['rateLimit'] ?? null;

        return \is_array($limit) && isset($limit['key']) ? $limit : null;
    }

    public function delete(): void
    {
        if ($this->deleted) {
            return;
        }
        $this->deleted = true;
        $this->deleteFromBackend();
    }

    public function release(int $delay = 0): void
    {
        $this->released = true;
        $this->releaseToBackend(\max(0, $delay));
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function isDeletedOrReleased(): bool
    {
        return $this->deleted || $this->released;
    }

    public function fail(?\Throwable $e = null): void
    {
        $this->failed = true;
        if (!$this->deleted) {
            $this->deleted = true;
            $this->deleteFromBackend();
        }
    }

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    // ─── backend (توسط هر درایور پیاده می‌شود) ────────────────────────────

    abstract public function attempts(): int;

    abstract public function getJobId(): int|string|null;

    abstract protected function deleteFromBackend(): void;

    abstract protected function releaseToBackend(int $delay): void;

    private function intOrNull(mixed $value): ?int
    {
        return \is_int($value) ? $value : (\is_numeric($value) ? (int) $value : null);
    }
}
