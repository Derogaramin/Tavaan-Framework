<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Support\Arrayable;
use Tavaan\Core\Contracts\Support\Jsonable;

/**
 * تَوان | Tavaan — Validation
 * MessageBag — ظرف خطاهای اعتبارسنجی (DOC-051 §7).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class MessageBag implements Arrayable, Jsonable, \Countable
{
    /** @var array<string, list<string>> */
    private array $messages = [];

    /**
     * @param array<string, list<string>> $messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $list) {
            foreach ($list as $message) {
                $this->add((string) $key, (string) $message);
            }
        }
    }

    public function add(string $key, string $message): void
    {
        if (!\in_array($message, $this->messages[$key] ?? [], true)) {
            $this->messages[$key][] = $message;
        }
    }

    /** @param array<string, list<string>> $messages */
    public function merge(array $messages): void
    {
        foreach ($messages as $key => $list) {
            foreach ($list as $message) {
                $this->add((string) $key, (string) $message);
            }
        }
    }

    public function has(?string $key = null): bool
    {
        if ($key === null) {
            return $this->messages !== [];
        }

        if (\str_contains($key, '*')) {
            $pattern = '/^' . \str_replace('\*', '.+', \preg_quote($key, '/')) . '$/';
            foreach (\array_keys($this->messages) as $existing) {
                if (\preg_match($pattern, $existing) === 1 && $this->messages[$existing] !== []) {
                    return true;
                }
            }

            return false;
        }

        return ($this->messages[$key] ?? []) !== [];
    }

    public function first(?string $key = null): string
    {
        if ($key === null) {
            foreach ($this->messages as $list) {
                if ($list !== []) {
                    return $list[0];
                }
            }

            return '';
        }

        return $this->messages[$key][0] ?? '';
    }

    /** @return list<string> */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /** @return list<string> همهٔ پیام‌ها (flat) */
    public function all(): array
    {
        $out = [];
        foreach ($this->messages as $list) {
            foreach ($list as $message) {
                $out[] = $message;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return \array_keys($this->messages);
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->messages;
    }

    public function toJson(int $flags = 0): string
    {
        return (string) \json_encode($this->messages, $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function count(): int
    {
        return \count($this->all());
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->messages !== [];
    }
}
