<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Validation;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * خطای اعتبارسنجی (422) همراه با فهرست خطاهای فیلدها.
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
class ValidationException extends HttpException
{
    /**
     * @param array<string, list<string>> $errors خطاها به تفکیک فیلد
     */
    public function __construct(
        public readonly array $errors = [],
        string $messageId = 'validation.failed',
        ?string $hint = null,
    ) {
        parent::__construct(422, $messageId, ['fields' => array_keys($errors)], $hint);
    }

    /** @return list<string> */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
