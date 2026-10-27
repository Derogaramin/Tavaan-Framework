<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/**
 * Fixture: job شکست‌خورندهٔ بدون override — `tries`/`backoff` در سطح job
 * تعریف نمی‌شود تا maxTries از WorkerOptions (فرمان `queue:work --tries=N`)
 * گرفته شود (برخلاف FailingJob که tries=3 دارد و release می‌شود).
 *
 * برای تست مسیر «شکست نهایی → failed_jobs» در QueueCommandsTest.
 */
final class PlainFailJob implements ShouldQueue
{
    use Queueable;

    /** @var list<string> تلاش‌های انجام‌شده */
    public static array $attempts = [];

    public function __construct(public readonly string $tag = 'plain-fail')
    {
    }

    public function handle(): void
    {
        self::$attempts[] = $this->tag;

        throw new \RuntimeException('شکست عمدی (بدون retry سطح job): ' . $this->tag);
    }

    public static function reset(): void
    {
        self::$attempts = [];
    }
}
