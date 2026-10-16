<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade ممیزی (Audit).
 *
 * @method static string record(\Tavaan\Engine\Audit\AuditEvent $event)
 * @method static \Tavaan\Engine\Audit\VerifyResult verify()
 * @method static string lastHash()
 * @method static array export(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $limit = 10000)
 * @method static int prune(int $olderThanDays)
 * @method static ?array find(string $auditId)
 * @mixin \Tavaan\Engine\Audit\Auditor
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class Audit extends Facade
{
    protected static function accessor(): string
    {
        return 'audit';
    }
}
