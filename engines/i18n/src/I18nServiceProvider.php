<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — I18n
 * I18nServiceProvider — ثبت Translator به‌عنوان `translator`.
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Translator::class, static function (ContainerInterface $c): Translator {
            $config = $c->bound('config') ? (array) $c->make('config')->get('i18n', []) : [];
            if ($config === []) {
                $config = [
                    'locale' => 'fa',
                    'fallback_locale' => 'en',
                    'paths' => [\defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/resources/lang' : ''],
                ];
            }

            return new Translator($config);
        });
        $this->app->alias(Translator::class, 'translator');
        $this->app->alias(Translator::class, 'lang');
        $this->app->alias(Translator::class, TranslatorInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Translator::class, 'translator', 'lang', TranslatorInterface::class];
    }
}
