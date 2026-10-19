<?php

declare(strict_types=1);

namespace Tavaan\Testing;

use Tavaan\Core\Exceptions\TavaanException;
use Tavaan\Testing\Database\Factory;
use Tavaan\Testing\Mock\Mock;

/**
 * تَوان | Tavaan — Testing
 * TestCase — پایهٔ تست با Assertionهای صفر‌وابستگی (DOC-084E §3).
 *
 * چرخهٔ هر متد تست (توسط Runner): `Mock::reset` → `setUp` → متد تست →
 * بررسی انتظار استثنا → `Mock::verifyAll` → `tearDown` → `Mock::reset`.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
abstract class TestCase
{
    private static int $assertionCount = 0;

    private ?string $expectedException = null;

    private ?string $expectedMessageId = null;

    private ?int $expectedCode = null;

    public function __construct()
    {
        // اتصال Model::factory() به قرارداد نام Factoryها (DOC-084E §6) — یک‌بار
        Factory::ensureModelResolver();
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    /**
     * اجرای یک متد تست توسط Runner (setUp → test → بررسی انتظار → tearDown).
     */
    final public function runBare(string $method): int
    {
        $this->expectedException = null;
        $this->expectedMessageId = null;
        $this->expectedCode = null;
        self::$assertionCount = 0;

        Mock::reset();
        $this->setUp();

        $thrown = null;
        try {
            $this->{$method}();
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        if ($thrown !== null) {
            try {
                $matched = $this->matchesExpectation($thrown);
            } catch (\Throwable $matchError) {
                $this->tearDown();
                Mock::reset();
                throw $matchError;
            }
            if (!$matched) {
                $this->tearDown();
                Mock::reset();
                throw $thrown;
            }
        }

        try {
            Mock::verifyAll();
            if ($this->expectedException !== null && $thrown === null) {
                throw AssertionFailed::for(\sprintf('Expected exception [%s] was not thrown.', $this->expectedException));
            }
        } finally {
            $this->tearDown();
            Mock::reset();
        }

        return self::$assertionCount;
    }

    private function matchesExpectation(\Throwable $e): bool
    {
        if ($this->expectedException === null) {
            return false;
        }
        if (!$e instanceof $this->expectedException) {
            return false;
        }
        if ($this->expectedMessageId !== null) {
            if (!$e instanceof TavaanException || $e->messageId !== $this->expectedMessageId) {
                throw AssertionFailed::for(\sprintf(
                    'Expected messageId [%s] but got [%s].',
                    $this->expectedMessageId,
                    $e instanceof TavaanException ? $e->messageId : '-',
                ));
            }
        }
        if ($this->expectedCode !== null && $e->getCode() !== $this->expectedCode) {
            throw AssertionFailed::for(\sprintf('Expected code [%d] but got [%d].', $this->expectedCode, $e->getCode()));
        }

        return true;
    }

    // ─── انتظارات ─────────────────────────────────────────────────────────

    /** @param class-string<\Throwable> $class */
    protected function expectException(string $class): void
    {
        $this->expectedException = $class;
    }

    protected function expectExceptionMessageId(string $messageId): void
    {
        $this->expectedMessageId = $messageId;
    }

    protected function expectExceptionCode(int $code): void
    {
        $this->expectedCode = $code;
    }

    // ─── Assertionها ──────────────────────────────────────────────────────

    public static function assertTrue(mixed $condition, string $message = ''): void
    {
        self::assertThat($condition === true, $message ?: 'Failed asserting that value is true.');
    }

    public static function assertFalse(mixed $condition, string $message = ''): void
    {
        self::assertThat($condition === false, $message ?: 'Failed asserting that value is false.');
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::assertThat(
            $expected === $actual,
            $message ?: \sprintf('Failed asserting that %s is identical to %s.', self::export($actual), self::export($expected)),
        );
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::assertThat($expected !== $actual, $message ?: 'Failed asserting that two values are not identical.');
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::assertThat(
            $expected == $actual,
            $message ?: \sprintf('Failed asserting that %s equals %s.', self::export($actual), self::export($expected)),
        );
    }

    public static function assertNull(mixed $value, string $message = ''): void
    {
        self::assertThat($value === null, $message ?: \sprintf('Failed asserting that %s is null.', self::export($value)));
    }

    public static function assertNotNull(mixed $value, string $message = ''): void
    {
        self::assertThat($value !== null, $message ?: 'Failed asserting that value is not null.');
    }

    /** @param \Countable|array<array-key, mixed> $haystack */
    public static function assertCount(int $expected, \Countable|array $haystack, string $message = ''): void
    {
        $actual = \count($haystack);
        self::assertThat($actual === $expected, $message ?: \sprintf('Failed asserting count %d === %d.', $actual, $expected));
    }

    /** @param iterable<array-key, mixed> $haystack */
    public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void
    {
        foreach ($haystack as $item) {
            if ($item === $needle) {
                self::assertThat(true);

                return;
            }
        }
        self::assertThat(false, $message ?: 'Failed asserting that iterable contains value.');
    }

    /** @param array<array-key, mixed> $array */
    public static function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        self::assertThat(\array_key_exists($key, $array), $message ?: \sprintf('Failed asserting that array has key [%s].', $key));
    }

    /** @param array<array-key, mixed> $array */
    public static function assertArrayNotHasKey(string|int $key, array $array, string $message = ''): void
    {
        self::assertThat(!\array_key_exists($key, $array), $message ?: \sprintf('Failed asserting that array does not have key [%s].', $key));
    }

    /** @param class-string $expected */
    public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void
    {
        self::assertThat($actual instanceof $expected, $message ?: \sprintf('Failed asserting that %s is instance of %s.', \get_debug_type($actual), $expected));
    }

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::assertThat(\str_contains($haystack, $needle), $message ?: \sprintf('Failed asserting that string contains [%s].', $needle));
    }

    public static function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        self::assertThat(!\str_contains($haystack, $needle), $message ?: \sprintf('Failed asserting that string does not contain [%s].', $needle));
    }

    public static function assertMatchesRegex(string $pattern, string $value, string $message = ''): void
    {
        self::assertThat(\preg_match($pattern, $value) === 1, $message ?: \sprintf('Failed asserting that [%s] matches %s.', $value, $pattern));
    }

    public static function assertGreaterThan(int|float $threshold, int|float $value, string $message = ''): void
    {
        self::assertThat($value > $threshold, $message ?: \sprintf('Failed asserting that %s > %s.', $value, $threshold));
    }

    public static function assertLessThan(int|float $threshold, int|float $value, string $message = ''): void
    {
        self::assertThat($value < $threshold, $message ?: \sprintf('Failed asserting that %s < %s.', $value, $threshold));
    }

    public static function assertEqualsWithDelta(float $expected, float $actual, float $delta, string $message = ''): void
    {
        self::assertThat(\abs($expected - $actual) <= $delta, $message ?: \sprintf('Failed asserting that %s ≈ %s (±%s).', $actual, $expected, $delta));
    }

    /** @param \Countable|array<array-key, mixed>|string|null $value */
    public static function assertEmpty(mixed $value, string $message = ''): void
    {
        self::assertThat(empty($value), $message ?: 'Failed asserting that value is empty.');
    }

    /** @param \Countable|array<array-key, mixed>|string|null $value */
    public static function assertNotEmpty(mixed $value, string $message = ''): void
    {
        self::assertThat(!empty($value), $message ?: 'Failed asserting that value is not empty.');
    }

    public static function fail(string $message = 'Test failed.'): never
    {
        throw AssertionFailed::for($message);
    }

    /**
     * هستهٔ همهٔ Assertionها (عمومی تا TestResponse و ابزارهای جانبی بشمارند).
     */
    public static function assertThat(bool $condition, string $message = ''): void
    {
        self::$assertionCount++;

        if (!$condition) {
            throw AssertionFailed::for($message);
        }
    }

    private static function export(mixed $value): string
    {
        if (\is_string($value)) {
            return "'" . \substr($value, 0, 60) . "'";
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (\is_array($value)) {
            return 'array(' . \count($value) . ')';
        }
        if (\is_object($value)) {
            return $value::class;
        }

        return (string) $value;
    }
}
