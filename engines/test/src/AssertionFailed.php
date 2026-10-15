<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * AssertionFailed — شکست یک Assertion (توسط Runner گرفته می‌شود).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class AssertionFailed extends \RuntimeException
{
    public static function for(string $message): self
    {
        return new self($message);
    }
}
