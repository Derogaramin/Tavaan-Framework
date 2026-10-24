<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade ترجمه (Lang — DOC-069).
 *
 * @method static string get(string $key, array $replace = [], ?string $locale = null)
 * @method static bool has(string $key, ?string $locale = null)
 * @method static string choice(string $key, int $number, array $replace = [], ?string $locale = null)
 * @mixin \Tavaan\Engine\I18n\Translator
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class Lang extends Facade
{
    protected static function accessor(): string
    {
        return 'translator';
    }
}
