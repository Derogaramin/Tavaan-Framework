<?php

declare(strict_types=1);

namespace Tavaan\Engine\Pipeline\Exceptions;

/**
 * عبور از حداکثر طول زنجیره (DoS protection).
 *
 * @api 1.0
 * @see docs/03-engines/pipeline.md
 */
final class PipelineMaxDepthException extends PipelineException
{
    public static function for(int $max): self
    {
        return new self(
            messageId: 'pipeline.max_depth',
            context: ['max' => $max],
            hint: 'طول زنجیرهٔ middleware را کاهش دهید یا pipeline.max_depth را بازبینی کنید.',
        );
    }
}
