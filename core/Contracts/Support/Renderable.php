<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Support;

/**
 * قابلیت رندر شدن به رشته/پاسخ.
 *
 * @api 1.0
 */
interface Renderable
{
    public function render(): mixed;
}
