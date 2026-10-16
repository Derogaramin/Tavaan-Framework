<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing\Exporters;

use Tavaan\Core\Contracts\Observability\SpanExporterInterface;
use Tavaan\Core\Contracts\Observability\SpanInterface;

/**
 * تَوان | Tavaan — Observability
 * ArrayExporter — ضبط Spanها در حافظه (تست/اشکال‌زدایی).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class ArrayExporter implements SpanExporterInterface
{
    /** @var list<array<string, mixed>> */
    private array $spans = [];

    public function export(SpanInterface $span): void
    {
        $this->spans[] = $span->toArray();
    }

    public function flush(): int
    {
        return 0;   // همه‌چیز از قبل در حافظه است
    }

    public function name(): string
    {
        return 'array';
    }

    /** @return list<array<string, mixed>> */
    public function spans(): array
    {
        return $this->spans;
    }

    public function last(): ?array
    {
        return $this->spans === [] ? null : $this->spans[\count($this->spans) - 1];
    }

    public function count(): int
    {
        return \count($this->spans);
    }

    public function clear(): void
    {
        $this->spans = [];
    }
}
