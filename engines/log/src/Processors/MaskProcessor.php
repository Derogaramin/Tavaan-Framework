<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Core\Error\LogMasker;
use Tavaan\Engine\Log\LogRecord;

/**
 * Mask دادهٔ حساس context (همیشه در زنجیرهٔ audit/security).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class MaskProcessor implements ProcessorInterface
{
    public function __construct(private readonly LogMasker $masker)
    {
    }

    public function process(LogRecord $record): LogRecord
    {
        return $record->withContext($this->masker->maskData($record->context));
    }
}
