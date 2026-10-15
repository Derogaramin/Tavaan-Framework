<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Support;

/**
 * قابلیت تبدیل به آرایه.
 *
 * @api 1.0
 */
interface Arrayable
{
    /** @return array<array-key, mixed> */
    public function toArray(): array;
}
