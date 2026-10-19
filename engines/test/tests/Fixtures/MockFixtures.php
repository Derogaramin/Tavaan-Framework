<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Fixtures;

/**
 * Fixtureهای تست موتور Test — Mock (DOC-084E §4).
 *
 * @api internal
 */

/**
 * اینترفیس سادهٔ شبیه Store برای Mockهای پایه.
 */
interface StoreFixtureInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /** @param list<string> $keys */
    public function pullMany(string ...$keys): array;
}

/**
 * انواع امضاهای چالش‌برانگیز برای ProxyGenerator (union/DNF/enum/static/void).
 */
interface ShapeFixtureInterface
{
    public function union(int|string $value): int|string;

    public function nullable(?int $value = null): ?int;

    public function withDefaults(int $limit = PHP_INT_MAX, array $options = ['a' => 1], ?string $name = null): int;

    public function dnf((CountableFixture&NamedFixture)|null $value): bool;

    public function withEnum(StatusFixtureEnum $status = StatusFixtureEnum::Active): string;

    public static function make(string $id): static;

    public function duplicate(): self;

    public function nothing(): void;
}

interface CountableFixture
{
    public function size(): int;
}

interface NamedFixture
{
    public function label(): string;
}

final class ShapeFixture implements CountableFixture, NamedFixture
{
    public function size(): int
    {
        return 1;
    }

    public function label(): string
    {
        return 'shape';
    }
}

enum StatusFixtureEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

/**
 * کلاس غیرنهایی با constructor واجب — Proxy نباید والد را صدا بزند.
 */
class ServiceFixture
{
    public function __construct(private readonly string $required)
    {
        if ($required === '') {
            throw new \InvalidArgumentException('required');
        }
    }

    public function getValue(): string
    {
        return $this->required;
    }

    public function setValue(string $value): void
    {
        // عمداً بی‌اثر — در Mock override می‌شود
    }
}

/**
 * کلاس final — Mock باید آن را رد کند (با پیام راهنما).
 */
final class FinalServiceFixture
{
    public function run(): void
    {
    }
}
