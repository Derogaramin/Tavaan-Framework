<?php

declare(strict_types=1);

namespace Tavaan\Core\Exceptions\Engine;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای موتور (حوزهٔ کد: EN).
 *
 * @api 1.0
 * @see docs/03-engines/README.md
 */
final class EngineNotInstalledException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $messageId = 'engine.not_installed',
        array $context = [],
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $context, $hint, null, 0, $previous);
    }
}
