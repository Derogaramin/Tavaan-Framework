<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Engine\Http\Exceptions\HeaderInjectionException;

/**
 * تَوان | Tavaan — Http
 * HeaderBag — دسترسی case-insensitive به headerها با محافظ injection.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class HeaderBag implements \Countable, \IteratorAggregate
{
    /** @var array<string, list<string>> lowercased name → values */
    private array $headers = [];

    /** @var array<string, string> lowercased → original case */
    private array $names = [];

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->set((string) $name, $value);
        }
    }

    /** @param string|list<string> $value */
    public function set(string $name, string|array $value): void
    {
        $this->assertSafe($name);
        $key = \strtolower($name);
        $this->names[$key] = $name;

        $values = (array) $value;
        foreach ($values as $v) {
            $this->assertSafeValue((string) $v);
        }
        $this->headers[$key] = \array_values($values);
    }

    public function add(string $name, string $value): void
    {
        $this->assertSafeValue($value);
        $key = \strtolower($name);
        $this->names[$key] ??= $name;
        $this->headers[$key][] = $value;
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $values = $this->headers[\strtolower($name)] ?? null;

        return $values === null || $values === [] ? $default : $values[0];
    }

    /** @return list<string> */
    public function all(string $name): array
    {
        return $this->headers[\strtolower($name)] ?? [];
    }

    public function has(string $name): bool
    {
        return isset($this->headers[\strtolower($name)]);
    }

    public function remove(string $name): void
    {
        $key = \strtolower($name);
        unset($this->headers[$key], $this->names[$key]);
    }

    /** @return array<string, list<string>> lowercased keys */
    public function toArray(): array
    {
        return $this->headers;
    }

    /** @return array<string, list<string>> با حفظ case اصلی */
    public function allPreserveCase(): array
    {
        $out = [];
        foreach ($this->headers as $key => $values) {
            $out[$this->names[$key] ?? $key] = $values;
        }

        return $out;
    }

    public function count(): int
    {
        return \count($this->headers);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->allPreserveCase());
    }

    /** 🔒 رد نام header با کاراکتر غیرمجاز (Header Injection). */
    private function assertSafe(string $name): void
    {
        if ($name === '' || \preg_match('/^[A-Za-z0-9\'()*+\-.^_`|~]+$/', $name) !== 1) {
            throw HeaderInjectionException::forName($name);
        }
    }

    /** 🔒 رد CRLF/NUL در مقدار (Header Injection). */
    private function assertSafeValue(string $value): void
    {
        if (\preg_match('/[\r\n\0]/', $value) === 1) {
            throw HeaderInjectionException::forValue($value);
        }
    }
}
