<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

/**
 * تَوان | Tavaan — Realtime
 * ChannelRegistry — ثبت و resolve الگوهای کانال (DOC-045 §3/§8).
 *
 * ```php
 * Broadcast::channel('orders.{orderId}', fn (User $u, int $orderId): bool => …);
 * Broadcast::channel('chat.*', fn (User $u, string $room): bool => …);
 * ```
 *
 * الگوها: `{param}` → capturing، `*` → wildcard یک‌سطحی.
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class ChannelRegistry
{
    /** @var array<string, array{pattern: string, regex: string, params: list<string>, callback: \Closure}> */
    private array $channels = [];

    /**
     * @param \Closure(mixed, mixed...): mixed $callback (user, ...params) → bool|array|null
     */
    public function channel(string $pattern, \Closure $callback): void
    {
        $pattern = $this->normalize($pattern);
        [$regex, $params] = $this->compile($pattern);

        $this->channels[$pattern] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $params,
            'callback' => $callback,
        ];
    }

    public function has(string $pattern): bool
    {
        return isset($this->channels[$this->normalize($pattern)]);
    }

    /** @return list<string> */
    public function patterns(): array
    {
        return \array_keys($this->channels);
    }

    /**
     * resolve کانال مشخص → callback + پارامترهای استخراج‌شده.
     *
     * @return array{callback: \Closure, params: list<string>, pattern: string}|null
     */
    public function resolve(string $channel): ?array
    {
        $channel = $this->normalize($channel);

        // تطبیق دقیق اول (صریح‌تر)
        if (isset($this->channels[$channel])) {
            return ['callback' => $this->channels[$channel]['callback'], 'params' => [], 'pattern' => $channel];
        }

        foreach ($this->channels as $entry) {
            if (\preg_match($entry['regex'], $channel, $m) === 1) {
                $params = [];
                foreach ($entry['params'] as $i => $name) {
                    $params[] = (string) ($m[$i + 1] ?? '');
                }

                return ['callback' => $entry['callback'], 'params' => $params, 'pattern' => $entry['pattern']];
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->channels = [];
    }

    /** private. → private: (Laravel-style) و حذف فاصله */
    private function normalize(string $channel): string
    {
        $channel = \trim($channel);
        if (\str_starts_with($channel, 'private.')) {
            $channel = 'private:' . \substr($channel, 8);
        } elseif (\str_starts_with($channel, 'presence.')) {
            $channel = 'presence:' . \substr($channel, 9);
        }

        return $channel;
    }

    /**
     * @return array{0: string, 1: list<string>} regex + نام پارامترها
     */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = '';
        $segments = \explode('.', $pattern);
        $compiled = [];

        foreach ($segments as $segment) {
            if ($segment === '*') {
                $compiled[] = '[^.]+';
                $params[] = 'wildcard';
                continue;
            }
            if (\preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $m) === 1) {
                $compiled[] = '([^./]+)';
                $params[] = $m[1];
                continue;
            }
            $compiled[] = \preg_quote($segment, '#');
        }
        $regex = '#^' . \implode('\.', $compiled) . '$#';

        return [$regex, $params];
    }
}
