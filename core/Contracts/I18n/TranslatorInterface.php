<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\I18n;

/**
 * قرارداد مترجم (DOC-014 §2.8).
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
interface TranslatorInterface
{
    /** @param array<string, mixed> $replace */
    public function get(string $key, array $replace = [], ?string $locale = null): string;

    public function has(string $key, ?string $locale = null): bool;

    /** @param array<string, mixed> $replace */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string;

    public function setLocale(string $locale): void;

    public function getLocale(): string;
}
