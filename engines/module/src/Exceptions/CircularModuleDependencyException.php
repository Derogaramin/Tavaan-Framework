<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module\Exceptions;

/**
 * چرخهٔ وابستگی بین ماژول‌ها.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class CircularModuleDependencyException extends ModuleException
{
    /** @param list<string> $modules */
    public static function for(array $modules): self
    {
        return new self(
            messageId: 'module.circular_dependency',
            context: ['modules' => $modules],
            hint: 'وابستگی چرخه‌ای بین ماژول‌ها را برطرف کنید.',
        );
    }
}
