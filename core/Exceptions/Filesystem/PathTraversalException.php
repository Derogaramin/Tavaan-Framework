<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Filesystem;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تلاش برای فرار از sandbox مسیر (حوزهٔ کد: FS — امنیتی).
 *
 * @api 1.0
 * @see docs/01-standards/security-policy.md
 */
final class PathTraversalException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'filesystem.path_traversal', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($messageId, $context, 'مسیر باید داخل sandbox مجاز بماند.', null, 0, $previous);
    }
}
