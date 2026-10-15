<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log\Processors;

use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Engine\Log\LogRecord;

/**
 * افزودن env/app_version/hostname.
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class EnvironmentProcessor implements ProcessorInterface
{
    public function process(LogRecord $record): LogRecord
    {
        return $record->withExtra([
            'app_version' => \defined('TVN_VERSION') ? (string) \TVN_VERSION : 'unknown',
            'hostname'    => \gethostname() ?: 'unknown',
            'sapi'        => PHP_SAPI,
        ]);
    }
}
