<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;

/**
 * تَوان | Tavaan — Core
 * BootstrapperInterface — یک گام از زنجیرهٔ راه‌اندازی.
 *
 * 🔒 Bootstrapperها باید idempotent باشند (AC-BOOT-09).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
interface BootstrapperInterface
{
    public function bootstrap(Application $app): void;
}
