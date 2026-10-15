<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * بدنهٔ درخواست بیش از حد مجاز (413).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class PayloadTooLargeException extends HttpException
{
    public static function for(int $size, int $max): self
    {
        return new self(413, 'http.payload.too_large', ['size' => $size, 'max' => $max]);
    }
}
