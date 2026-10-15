<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Http;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * پایهٔ استثناهای دارای وضعیت HTTP (حوزهٔ کد: HT).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
abstract class HttpException extends TavaanException
{
    /**
     * @param int                    $statusCode وضعیت HTTP
     * @param array<string, string>  $headers    هدرهای اضافی پاسخ
     */
    public function __construct(
        public readonly int $statusCode = 500,
        string $messageId = 'http.error',
        array $context = [],
        ?string $hint = null,
        ?string $docsUrl = null,
        public readonly array $headers = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, $docsUrl, $code, $previous);
    }
}
