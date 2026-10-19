<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestClassDescriptor — توصیف یک کلاس تست (نام، فایل، متدها) در TestSuite.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final readonly class TestClassDescriptor
{
    /**
     * @param class-string<TestCase>          $class
     * @param list<TestMethodDescriptor> $methods
     */
    public function __construct(
        public string $class,
        public string $file,
        public array $methods,
    ) {
    }

    public function shortName(): string
    {
        $parts = \explode('\\', $this->class);

        return (string) \end($parts);
    }
}
