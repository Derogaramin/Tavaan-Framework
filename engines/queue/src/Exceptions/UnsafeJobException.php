<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Queue
 * UnsafeJobException — کلاس job در allowlist نیست (DOC-061 §7: Serialization امن).
 *
 * 🔒 هرگز کلاس ناشناخته را از payload بازسازی نکن.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class UnsafeJobException extends TavaanException
{
    public function __construct(string $class, ?string $hint = null)
    {
        parent::__construct(
            'queue.unsafe_job',
            ['class' => $class],
            $hint ?? 'کلاس job در allowlist صف نیست (config: queue.allowlist).',
        );
    }
}
