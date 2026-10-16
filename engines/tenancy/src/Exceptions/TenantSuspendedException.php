<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantSuspendedException — tenant غیرفعال/معلق است (403).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantSuspendedException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(403, 'tenancy.suspended', $context, $hint);
    }
}
