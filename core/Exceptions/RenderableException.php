<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

/**
 * استثنایی که قادر است پاسخ/خروجی خودش را رندر کند.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
interface RenderableException
{
    /**
     * رندر پاسخ؛ `null` یعنی Handler خودش رندر کند.
     *
     * @return mixed پاسخ (در M2: ResponseInterface) یا null
     */
    public function render(): mixed;
}
