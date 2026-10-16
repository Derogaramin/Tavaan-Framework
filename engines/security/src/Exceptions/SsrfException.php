<?php

declare(strict_types=1);

namespace Tavaan\Engine\Security\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Security
 * SsrfException — URL خروجی ناامن (DOC-059 §2.5).
 *
 * @api 1.0
 * @see docs/03-engines/security.md
 */
final class SsrfException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'security.ssrf.blocked', array $context = [], ?string $hint = null)
    {
        parent::__construct($messageId, $context, $hint ?? 'URL خروجی به محدودهٔ نامجاز اشاره دارد.');
    }
}
