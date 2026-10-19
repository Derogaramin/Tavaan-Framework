<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

/**
 * تَوان | Tavaan — Plugin
 * ScanReport — نتیجهٔ اسکن توکنی کد افزونه (DOC-079 §5.1).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final readonly class ScanReport
{
    /**
     * @param list<array{file: string, line: int, func: string}> $findings
     */
    public function __construct(public array $findings, public int $scannedFiles = 0)
    {
    }

    public function clean(): bool
    {
        return $this->findings === [];
    }

    public function count(): int
    {
        return \count($this->findings);
    }

    /** خلاصهٔ خوانا برای CLI/استثنا. @return list<string> */
    public function summary(int $limit = 20): array
    {
        $lines = [];
        foreach (\array_slice($this->findings, 0, $limit) as $finding) {
            $lines[] = \sprintf('%s:%d → %s()', $finding['file'], $finding['line'], $finding['func']);
        }
        if (\count($this->findings) > $limit) {
            $lines[] = '… و ' . (\count($this->findings) - $limit) . ' مورد دیگر';
        }

        return $lines;
    }
}
