<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Filesystem;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * فایل یافت نشد (حوزهٔ کد: FS).
 *
 * @api 1.0
 * @see docs/01-standards/error-handling.md
 */
class FileNotFoundException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'filesystem.file_not_found', array $context = [], ?string $hint = null, ?\Throwable $previous = null)
    {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }
}
