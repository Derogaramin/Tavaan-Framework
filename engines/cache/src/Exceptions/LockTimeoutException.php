<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Cache
 * LockTimeoutException — قفل در مهلت مقرر تصاحب نشد (DOC-060 §4).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class LockTimeoutException extends TavaanException
{
    public function __construct(string $name = '', ?string $hint = null)
    {
        parent::__construct('cache.lock_timeout', ['lock' => $name], $hint ?? 'قفل در مهلت مقرر آزاد نشد.');
    }
}
