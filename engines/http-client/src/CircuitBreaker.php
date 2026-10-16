<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Engine\HttpClient\Exceptions\CircuitOpenException;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — HttpClient
 * CircuitBreaker — قطع‌کنندهٔ مدار بر پایهٔ cache (DOC-064 §4).
 *
 * حالت‌ها: Closed → (۵ خطا در ۶۰s) → Open → (پس از ۳۰s cooldown) →
 * Half-Open → (موفقیت) Closed / (خطا) Open.
 *
 * بدون cache → غیرفعال (همیشه Closed؛ degrade ایمن).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class CircuitBreaker
{
    public function __construct(
        private readonly ?CacheRepository $cache = null,
        private readonly int $threshold = 5,
        private readonly int $window = 60,
        private readonly int $cooldown = 30,
    ) {
    }

    /**
     * اجرای action تحت محافظت مدار.
     *
     * @template T
     *
     * @param \Closure(): T $action
     *
     * @return T
     *
     * @throws CircuitOpenException وقتی مدار باز است
     */
    public function call(string $service, \Closure $action): mixed
    {
        if ($this->cache === null) {
            return $action();
        }

        $state = $this->state($service);

        if ($state === 'open') {
            if (!$this->shouldTryHalfOpen($service)) {
                throw new CircuitOpenException($service, $this->cooldown);
            }
            // half-open: اجازهٔ یک درخواست آزمایشی
        }

        try {
            $result = $action();
            $this->recordSuccess($service);

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($service);

            throw $e;
        }
    }

    public function state(string $service): string
    {
        if ($this->cache === null) {
            return 'closed';
        }

        $record = $this->read($service);
        if ($record === null) {
            return 'closed';
        }

        if (($record['state'] ?? 'closed') === 'open') {
            $openedAt = (int) ($record['opened_at'] ?? 0);
            if (Clock::timestamp() - $openedAt >= $this->cooldown) {
                return 'half-open';
            }

            return 'open';
        }

        return 'closed';
    }

    /** بازنشانی دستی (تست/بازیابی). */
    public function reset(string $service): void
    {
        $this->cache?->forget($this->key($service));
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function shouldTryHalfOpen(string $service): bool
    {
        return $this->state($service) === 'half-open';
    }

    private function recordSuccess(string $service): void
    {
        $this->cache?->forget($this->key($service));   // → closed
    }

    private function recordFailure(string $service): void
    {
        if ($this->cache === null) {
            return;
        }

        $now = Clock::timestamp();
        $record = $this->read($service) ?? ['failures' => [], 'state' => 'closed', 'opened_at' => 0];

        // half-open با یک خطا → فوراً open
        $state = (string) ($record['state'] ?? 'closed');
        if ($state === 'open' || $this->state($service) === 'half-open') {
            $this->write($service, ['failures' => [], 'state' => 'open', 'opened_at' => $now]);

            return;
        }

        $failures = \array_values(\array_filter(
            (array) ($record['failures'] ?? []),
            fn ($ts): bool => (int) $ts > $now - $this->window,   // فقط پنجرهٔ جاری
        ));
        $failures[] = $now;

        if (\count($failures) >= $this->threshold) {
            $this->write($service, ['failures' => $failures, 'state' => 'open', 'opened_at' => $now]);

            return;
        }

        $this->write($service, ['failures' => $failures, 'state' => 'closed', 'opened_at' => 0]);
    }

    /** @return array{failures: list<int>, state: string, opened_at: int}|null */
    private function read(string $service): ?array
    {
        $raw = $this->cache?->get($this->key($service));

        return \is_array($raw) ? $raw : null;
    }

    /** @param array{failures: list<int>, state: string, opened_at: int} $record */
    private function write(string $service, array $record): void
    {
        $this->cache?->put($this->key($service), $record, $this->window + $this->cooldown + 60);
    }

    private function key(string $service): string
    {
        return 'tvn:http:circuit:' . $service;
    }
}
