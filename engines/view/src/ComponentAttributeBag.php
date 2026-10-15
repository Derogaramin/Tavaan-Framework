<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

/**
 * تَوان | Tavaan — View
 * ComponentAttributeBag — attributeهای اضافهٔ کامپوننت (`{{ $attributes }}`).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class ComponentAttributeBag implements \Stringable, \ArrayAccess, \IteratorAggregate
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private readonly array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function merge(array $attributes): self
    {
        return new self([...$this->attributes, ...$attributes]);
    }

    /** @param list<string> $keys */
    public function only(array $keys): self
    {
        return new self(\array_intersect_key($this->attributes, \array_flip($keys)));
    }

    /** @param list<string> $keys */
    public function except(array $keys): self
    {
        return new self(\array_diff_key($this->attributes, \array_flip($keys)));
    }

    /** رندر به‌صورت attributeهای HTML (escape شده). */
    public function toHtml(): string
    {
        $out = [];
        foreach ($this->attributes as $key => $value) {
            if ($value === true) {
                $out[] = Escaper::attr($key);
                continue;
            }
            if ($value === false || $value === null) {
                continue;
            }
            $out[] = Escaper::attr((string) $key) . '="' . Escaper::attr((string) $value) . '"';
        }

        return \implode(' ', $out);
    }

    public function __toString(): string
    {
        return $this->toHtml();
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
        throw new \BadMethodCallException('ComponentAttributeBag is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('ComponentAttributeBag is immutable.');
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->attributes);
    }
}
