<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Context\IdentityContext;
use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * افزودن user_id/tenant_id جاری.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class IdentityProcessor implements ProcessorInterface
{
    public function process(LogRecord $record): LogRecord
    {
        return $record->withIdentity(IdentityContext::userId(), IdentityContext::tenantId());
    }
}
