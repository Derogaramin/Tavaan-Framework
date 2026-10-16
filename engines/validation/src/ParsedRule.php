<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Validation\RuleInterface;

/**
 * تَوان | Tavaan — Validation
 * ParsedRule — یک قاعدهٔ تجزیه‌شده (نام + پارامترها یا Rule object).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final readonly class ParsedRule
{
    /**
     * @param list<string> $parameters
     */
    public function __construct(
        public string $name,
        public array $parameters = [],
        public ?RuleInterface $object = null,
        public ?\Closure $callback = null,
    ) {
    }

    public function isObject(): bool
    {
        return $this->object !== null;
    }

    public function isCallback(): bool
    {
        return $this->callback !== null;
    }
}
