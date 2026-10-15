<?php

declare(strict_types=1);

namespace Tavaan\Engine\Pipeline\Exceptions;

/**
 * Pipe/alias نامعتبر در زنجیره.
 *
 * @api 1.0
 * @see docs/03-engines/pipeline.md
 */
final class PipelinePipeNotFoundException extends PipelineException
{
    public static function for(string $pipe): self
    {
        return new self(
            messageId: 'pipeline.pipe_not_found',
            context: ['pipe' => $pipe],
            hint: 'alias را در pipeline.aliases ثبت کنید یا class-string معتبر بدهید.',
        );
    }
}
