<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — Http
 * ParameterBag — نگهدارندهٔ پارامترها (query/post/attributes) با dot-access.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class ParameterBag implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /** @param array<string, mixed> $parameters */
    public function __construct(private array $parameters = [])
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->parameters;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->parameters, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Arr::set($this->parameters, $key, $value);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->parameters, $key);
    }

    public function remove(string $key): void
    {
        Arr::forget($this->parameters, $key);
    }

    /** @param array<string, mixed> $parameters */
    public function replace(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /** @param array<string, mixed> $parameters */
    public function add(array $parameters): void
    {
        $this->parameters = [...$this->parameters, ...$parameters];
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return \is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return \is_numeric($value) ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : \filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function count(): int
    {
        return \count($this->parameters);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->parameters);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }
}
