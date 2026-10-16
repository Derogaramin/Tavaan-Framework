<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Engine\HttpClient\Exceptions\ConnectionException;

/**
 * تَوان | Tavaan — HttpClient
 * RetryHandler — تلاش مجدد با backoff نمایی + jitter (DOC-064 §5).
 *
 * 🔒 فقط خطاهای retryable: timeout/اتصال، 5xx و 429 (با احترام به Retry-After).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class RetryHandler
{
    /**
     * @param int                       $times     تعداد کل تلاش‌ها (۱ = بدون retry)
     * @param int                       $sleepMs   پایهٔ backoff (میلی‌ثانیه)
     * @param \Closure(int): int|null   $sleeper   تزریق خواب (تست) — پیش‌فرض usleep
     * @param \Closure(int): int|null   $jitter    تزریق jitter (تست)
     */
    public function __construct(
        private readonly int $times = 1,
        private readonly int $sleepMs = 100,
        private readonly int $maxSleepMs = 30_000,
        private readonly ?\Closure $sleeper = null,
        private readonly ?\Closure $jitter = null,
    ) {
    }

    /**
     * اجرای callback با retry.
     *
     * @template T
     *
     * @param \Closure(): T            $callback
     * @param \Closure(T): bool|null   $shouldRetryResponse آیا پاسخ دریافتی retry شود؟ (status-based)
     * @param \Closure(T): int|null    $retryAfterSeconds   استخراج Retry-After از پاسخ
     *
     * @return T
     */
    public function run(\Closure $callback, ?\Closure $shouldRetryResponse = null, ?\Closure $retryAfterSeconds = null): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $result = $callback();
            } catch (ConnectionException $e) {
                // خطای اتصال/timeout → retryable
                if ($attempt >= $this->times) {
                    throw $e;
                }
                $this->sleep($this->backoffMs($attempt));
                continue;
            }

            if ($shouldRetryResponse !== null && $attempt < $this->times && $shouldRetryResponse($result)) {
                $retryAfter = $retryAfterSeconds !== null ? $retryAfterSeconds($result) : 0;
                $this->sleep($retryAfter > 0 ? $retryAfter * 1000 : $this->backoffMs($attempt));
                continue;
            }

            return $result;
        }
    }

    /** backoff نمایی + jitter (DOC-064 §5). */
    public function backoffMs(int $attempt): int
    {
        $base = $this->sleepMs * (2 ** \max(0, $attempt - 1));
        $jitter = $this->jitter !== null
            ? ($this->jitter)($base)
            : (int) ($base * 0.2 * (\random_int(0, 100) / 100));

        return (int) \min($base + $jitter, $this->maxSleepMs);
    }

    private function sleep(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }

        if ($this->sleeper !== null) {
            ($this->sleeper)($ms);

            return;
        }

        \usleep($ms * 1000);
    }
}
