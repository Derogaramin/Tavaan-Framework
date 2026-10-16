<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing\Exporters;

use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Observability\SpanExporterInterface;
use Tavaan\Core\Contracts\Observability\SpanInterface;

/**
 * تَوان | Tavaan — Observability
 * LogExporter — صدور Span به‌صورت رکورد لاگ ساختاریافته.
 *
 * 🔒 attributeهای حساس با کلیدهای mask در پیکربندی حذف می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class LogExporter implements SpanExporterInterface
{
    /** الگوهای کلید attribute که باید mask شوند (DOC-067 §7) */
    private const MASK_PATTERNS = ['password', 'secret', 'token', 'authorization', 'api_key', 'apikey', 'cookie', 'credit'];

    private int $buffered = 0;

    /** @param list<string> $extraMask کلیدهای اضافی برای mask */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $extraMask = [],
    ) {
    }

    public function export(SpanInterface $span): void
    {
        $data = $span->toArray();
        $data['attributes'] = $this->maskAttributes((array) $data['attributes']);

        $this->logger->debug('trace.span', $data);
        $this->buffered++;
    }

    public function flush(): int
    {
        $n = $this->buffered;
        $this->buffered = 0;

        return $n;
    }

    public function name(): string
    {
        return 'log';
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function maskAttributes(array $attributes): array
    {
        $patterns = [...self::MASK_PATTERNS, ...$this->extraMask];
        $out = [];
        foreach ($attributes as $k => $v) {
            $lower = \mb_strtolower((string) $k);
            $masked = false;
            foreach ($patterns as $p) {
                if ($p !== '' && \str_contains($lower, \mb_strtolower((string) $p))) {
                    $masked = true;

                    break;
                }
            }
            $out[(string) $k] = $masked ? '***' : $v;
        }

        return $out;
    }
}
