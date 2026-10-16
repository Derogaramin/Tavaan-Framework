<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade صف (Queue).
 *
 * @method static int|string|null push(object|string $job, array $data = [], ?string $queue = null)
 * @method static int|string|null pushOn(string $queue, object|string $job, array $data = [])
 * @method static int|string|null later(\DateTimeInterface|int $delay, object|string $job, array $data = [], ?string $queue = null)
 * @method static int|string|null laterOn(string $queue, \DateTimeInterface|int $delay, object|string $job, array $data = [])
 * @method static \Tavaan\Core\Contracts\Queue\JobInterface|null pop(?string $queue = null)
 * @method static int size(?string $queue = null)
 * @method static \Tavaan\Core\Contracts\Queue\QueueInterface connection(?string $name = null)
 * @method static \Tavaan\Engine\Queue\Batch\PendingBatch batch(iterable $jobs)
 * @method static \Tavaan\Engine\Queue\Batch\PendingChain chain(iterable $jobs)
 * @method static \Tavaan\Engine\Queue\Batch\Batch|null findBatch(string $id)
 * @method static int worker(array|\Tavaan\Engine\Queue\WorkerOptions $options = [])
 * @method static string getDefaultConnection()
 * @mixin \Tavaan\Engine\Queue\QueueManager
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class Queue extends Facade
{
    protected static function accessor(): string
    {
        return 'queue';
    }
}
