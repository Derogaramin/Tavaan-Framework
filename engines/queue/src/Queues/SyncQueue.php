<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Queue\FailedJobProviderInterface;
use Tavaan\Core\Contracts\Queue\JobInterface;
use Tavaan\Engine\Queue\Events\JobFailed;
use Tavaan\Engine\Queue\Events\JobProcessed;
use Tavaan\Engine\Queue\Events\JobProcessing;
use Tavaan\Engine\Queue\Events\JobQueued;
use Tavaan\Engine\Queue\Jobs\SyncJob;
use Tavaan\Engine\Queue\Serializer;

/**
 * تَوان | Tavaan — Queue
 * SyncQueue — اجرای همزمان job در همان فرآیند (تست/dev؛ DOC-061 §2).
 *
 * معناگر Worker به‌صورت inline بازتولید می‌شود:
 * رویدادها (JobQueued/Processing/Processed/Failed)، failed provider و
 * hook کاربر `failed()`. 🔒 retry ندارد — استثنا پس از ثبت، rethrow می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class SyncQueue extends Queue
{
    public function __construct(
        string $connectionName,
        array $config,
        Serializer $serializer,
        ?ContainerInterface $container = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?FailedJobProviderInterface $failedProvider = null,
    ) {
        parent::__construct($connectionName, $config, $serializer, $container);
    }

    public function pushRaw(string $payload, ?string $queue = null): int|string|null
    {
        return $this->runInline($payload, $queue);
    }

    /**
     * درایور sync تأخیر را نادیده می‌گیرد و **بلافاصله** اجرا می‌کند.
     */
    public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null
    {
        return $this->runInline($payload, $queue);
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        return null;    // هرگز چیزی باقی نمی‌ماند
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }

    private function runInline(string $payload, ?string $queue): ?string
    {
        $decoded = $this->serializer->decodePayload($payload);
        $queueName = $queue ?? $this->resolveQueueFromPayload($decoded);

        $job = new SyncJob($this->container, $this->connectionName, $queueName, $payload, (string) ($decoded['uuid'] ?? ''));

        $this->events?->dispatch(new JobQueued($this->connectionName, $queueName, $job->getJobId(), $decoded));
        $this->events?->dispatch(new JobProcessing($this->connectionName, $job));

        try {
            $job->fire();
        } catch (\Throwable $e) {
            $job->fail($e);

            $uuid = $this->failedProvider?->log($this->connectionName, $queueName, $payload, $e, $job->getJobId())
                ?? (string) ($decoded['uuid'] ?? '');

            // hook کاربر failed(Throwable) از طریق wrapper
            $this->callWrapperFailed($decoded, $e);

            $this->events?->dispatch(new JobFailed($this->connectionName, $job, $e, $uuid));

            throw $e;   // 🔒 مثل Laravel: خطای sync به تماس‌زننده برمی‌گردد
        }

        if (!$job->isDeletedOrReleased()) {
            $job->delete();
        }

        $this->events?->dispatch(new JobProcessed($this->connectionName, $job));

        return (string) ($decoded['uuid'] ?? '');
    }

    /** @param array<string, mixed> $decoded */
    private function resolveQueueFromPayload(array $decoded): string
    {
        $queue = $decoded['queue'] ?? null;

        return \is_string($queue) && $queue !== '' ? $queue : $this->defaultQueue();
    }

    /** @param array<string, mixed> $decoded */
    private function callWrapperFailed(array $decoded, \Throwable $e): void
    {
        if ($this->container === null) {
            return;
        }

        try {
            $wrapperClass = (string) ($decoded['job'] ?? '');
            if ($wrapperClass === '' || !\class_exists($wrapperClass)) {
                return;
            }
            $wrapper = $this->container->make($wrapperClass);
            if (\method_exists($wrapper, 'failed')) {
                $wrapper->failed($decoded, $e);
            }
        } catch (\Throwable) {
            // 🔒 خطای hook نباید خطای اصلی را بپوشاند
        }
    }
}
