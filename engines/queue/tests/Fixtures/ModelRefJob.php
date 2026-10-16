<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Tests\Fixtures;

use Tavaan\Core\Contracts\Queue\ShouldQueue;
use Tavaan\Engine\Queue\Queueable;
use Tavaan\Engine\Queue\SerializesModels;

/** Fixture: job با مدل ORM — payload فقط «کلاس+کلید» (DOC-061 §7). */
final class ModelRefJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** @var list<string> */
    public static array $trace = [];

    public function __construct(public readonly QueueUser $user)
    {
    }

    public function handle(): void
    {
        self::$trace[] = 'user:' . $this->user->getKey() . ':' . (string) $this->user->name;
    }

    public static function reset(): void
    {
        self::$trace = [];
    }
}
