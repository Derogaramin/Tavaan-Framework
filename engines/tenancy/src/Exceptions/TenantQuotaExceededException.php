<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantQuotaExceededException — سهمیهٔ tenant پر شده است (429).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantQuotaExceededException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(429, 'tenancy.quota_exceeded', $context, $hint);
    }
}
