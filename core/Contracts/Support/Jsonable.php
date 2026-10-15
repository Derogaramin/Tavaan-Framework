<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Support;

/**
 * قابلیت تبدیل به JSON.
 *
 * @api 1.0
 */
interface Jsonable
{
    public function toJson(int $flags = 0): string;
}
