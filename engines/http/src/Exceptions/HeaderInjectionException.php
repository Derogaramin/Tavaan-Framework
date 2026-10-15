<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تلاش برای تزریق CRLF/کاراکتر غیرمجاز در header (حوزهٔ کد: HT).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class HeaderInjectionException extends HttpException
{
    public static function forName(string $name): self
    {
        return new self(400, 'http.header.injection', ['header' => \substr($name, 0, 40)]);
    }

    public static function forValue(string $value): self
    {
        return new self(400, 'http.header.injection', ['value' => \substr(\preg_replace('/[\r\n\0]/', '?', $value) ?? '', 0, 40)]);
    }
}
