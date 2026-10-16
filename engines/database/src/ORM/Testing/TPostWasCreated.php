<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

/**
 * Fixture تست ORM — Domain Event (dispatchesEvents — T-10).
 *
 * @api internal
 */
final readonly class TPostWasCreated
{
    public function __construct(public TPost $post)
    {
    }
}
