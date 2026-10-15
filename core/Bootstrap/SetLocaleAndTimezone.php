<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Core
 * SetLocaleAndTimezone — تنظیم timezone و locale از config.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class SetLocaleAndTimezone implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        if (!$app->bound('config')) {
            return;
        }

        $config = $app->make('config');

        $timezone = (string) $config->get('app.timezone', 'UTC');
        if (\in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            \date_default_timezone_set($timezone);
            Clock::setTimezone($timezone);
        }

        $locale = (string) $config->get('app.locale', 'fa');
        \setlocale(LC_NUMERIC, 'C');   // 🔒 اعداد در محاسبات همیشه لاتین
        \setlocale(LC_TIME, $locale . '.UTF-8', $locale);
    }
}
