<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Sse;

use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Engine\Http\StreamedResponse;
use Tavaan\Engine\Realtime\RealtimeManager;

/**
 * تَوان | Tavaan — Realtime
 * SseStreamer — تولید پاسخ Server-Sent Events (بدون هیچ ext — DOC-045 §4).
 *
 * قالب روی سیم (W3C SSE):
 * ```
 * retry: 3000
 *
 * event: order.status
 * id: 42
 * data: {"status":"paid"}
 *
 * : keep-alive
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class SseStreamer
{
    /**
     * @param array<string, mixed> $config کلید realtime.sse
     */
    public function __construct(
        private readonly RealtimeManager $manager,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت StreamedResponse با هدرهای صحیح SSE.
     *
     * @param list<string> $channels
     */
    public function response(array $channels, ?int $afterId = null, int $maxSeconds = 0): StreamedResponse
    {
        $heartbeat = \max(1, (int) ($this->config['heartbeat'] ?? 15));
        $retryMs = \max(1000, (int) ($this->config['retry_ms'] ?? 3000));
        // در محیط وب maxSeconds=0 یعنی «تا قطع کلاینت»؛ برای تست مقدار مثبت می‌گیرد
        $maxRuntime = $maxSeconds > 0
            ? $maxSeconds
            : (int) ($this->config['max_runtime'] ?? 0);

        $broker = $this->manager->broker();
        $startId = $afterId ?? $broker->lastId();

        $callback = function () use ($channels, $broker, $startId, $heartbeat, $retryMs, $maxRuntime): void {
            $this->streamTo($channels, $broker, $startId, $heartbeat, $retryMs, $maxRuntime);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',   // 🔒 غیرفعال‌کردن buffer در nginx
        ]);
    }

    /**
     * حلقهٔ اصلی stream — خروجی را echo می‌کند.
     *
     * - `$maxRuntime === 0` → تا قطع کلاینت (حالت وب)
     * - `$maxRuntime > 0` → یک پاس bounded (حالت تست/CLI کوتاه)
     *
     * @param list<string> $channels
     */
    public function streamTo(
        array $channels,
        BrokerInterface $broker,
        int $startId,
        int $heartbeat,
        int $retryMs,
        int $maxRuntime = 0,
    ): void {
        \set_time_limit(0);
        if (\function_exists('ob_implicit_flush')) {
            @\ob_implicit_flush(true);
        }

        echo self::formatRetry($retryMs);
        $this->flush();

        $cursor = $startId;
        $lastBeat = \time();
        $startedAt = \time();

        while (true) {
            $events = $broker->poll($channels, $maxRuntime > 0 ? 0 : 1, $cursor);
            foreach ($events as $event) {
                echo self::formatEvent(
                    (string) ($event['name'] ?? 'message'),
                    (array) ($event['payload'] ?? []),
                    (int) ($event['id'] ?? 0),
                );
                $cursor = \max($cursor, (int) ($event['id'] ?? 0));
                $lastBeat = \time();
            }
            $this->flush();

            if ($maxRuntime > 0) {
                // حالت bounded: یک پاس کامل + heartbeat نهایی و پایان
                if (\time() - $lastBeat >= $heartbeat) {
                    echo ": keep-alive\n\n";
                }
                break;
            }

            // heartbeat
            if (\time() - $lastBeat >= $heartbeat) {
                echo ": keep-alive\n\n";
                $this->flush();
                $lastBeat = \time();
            }

            if (\connection_aborted()) {
                break;
            }
            if (\time() - $startedAt > 86400) {
                break;   // سقف ایمن ۲۴ ساعته
            }
        }
    }

    // ─── قالب‌بندی پروتکل ────────────────────────────────────────────────

    /**
     * یک رویداد SSE — data چندخطی به چند `data:` شکسته می‌شود.
     *
     * @param array<string, mixed> $payload
     */
    public static function formatEvent(string $name, array $payload, ?int $id = null): string
    {
        $json = (string) \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $out = '';
        if ($id !== null && $id > 0) {
            $out .= 'id: ' . $id . "\n";
        }
        // 🔒 نام رویداد sanitize می‌شود (بدون \r\n — جلوی تزریق خط)
        $safeName = (string) \preg_replace('/[\r\n:]+/', '.', $name);
        $out .= 'event: ' . $safeName . "\n";
        foreach (\explode("\n", $json) as $line) {
            $out .= 'data: ' . $line . "\n";
        }

        return $out . "\n";
    }

    public static function formatRetry(int $milliseconds): string
    {
        return 'retry: ' . $milliseconds . "\n\n";
    }

    public static function formatComment(string $text): string
    {
        return ': ' . \str_replace(["\r", "\n"], ' ', $text) . "\n\n";
    }

    private function flush(): void
    {
        if (\ob_get_level() > 0 && \in_array(\PHP_SAPI, ['cli', 'cli-server', 'phpdbg'], true) === false) {
            @\ob_flush();
        }
        @\flush();
    }
}
