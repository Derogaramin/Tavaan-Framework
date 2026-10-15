<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Console;

/**
 * قرارداد خروجی CLI.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
interface OutputInterface
{
    public function write(string $text, string $style = ''): void;

    public function writeln(string $text = '', string $style = ''): void;

    public function newLine(int $count = 1): void;

    /**
     * @param list<string>                 $headers
     * @param list<array<array-key, mixed>> $rows
     */
    public function table(array $headers, array $rows): void;

    public function supportsColor(): bool;
}
