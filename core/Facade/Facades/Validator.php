<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade اعتبارسنجی (Validator).
 *
 * @method static \Tavaan\Engine\Validation\Validator make(array $data, array $rules, array $messages = [], array $attributes = [])
 * @method static array validate(array $data, array $rules, array $messages = [], array $attributes = [])
 * @method static void extend(string $rule, callable $extension, ?string $message = null)
 * @method static void replacer(string $rule, callable $replacer)
 * @method static \Tavaan\Engine\Validation\DtoValidator forDto(string $class, array $data)
 * @mixin \Tavaan\Engine\Validation\ValidatorFactory
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class Validator extends Facade
{
    protected static function accessor(): string
    {
        return 'validator';
    }
}
