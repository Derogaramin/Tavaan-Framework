<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Console;

/**
 * قرارداد ورودی CLI.
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
interface InputInterface
{
    public function argument(string $name, mixed $default = null): mixed;

    public function option(string $name, mixed $default = null): mixed;

    public function hasOption(string $name): bool;

    /** @return array<string, mixed> */
    public function arguments(): array;

    /** @return array<string, mixed> */
    public function options(): array;

    /** @return list<string> توکن‌های خام */
    public function rawTokens(): array;

    /** مقدار دهی ورودی (برای تست: ArrayInput). */
    public function setInteractiveInput(string $value): void;

    public function readLine(string $prompt = ''): ?string;
}
