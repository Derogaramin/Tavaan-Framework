<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;

/** Fixture: job همیشه شکست‌خورنده (retry/backoff/failed hook). */
final class FailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> backoff فهرستی — DOC-061 §4 */
    public array $backoff = [1, 2];

    /** @var list<int> تلاش‌های انجام‌شده */
    public static array $attempts = [];

    /** @var list<string> فراخوانی‌های failed() */
    public static array $failedCalls = [];

    public function __construct(public readonly string $tag = 'fail')
    {
    }

    public function handle(): void
    {
        self::$attempts[] = $this->tag;

        throw new \RuntimeException('job عمدی شکست خورد: ' . $this->tag);
    }

    public function failed(\Throwable $e): void
    {
        self::$failedCalls[] = $this->tag . ':' . $e->getMessage();
    }

    public static function reset(): void
    {
        self::$attempts = [];
        self::$failedCalls = [];
    }
}
