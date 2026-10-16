<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Exceptions;

use Tavaan\Core\Exceptions\Http\HttpException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantNotFoundException — tenant از resolver یافت نشد (404).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantNotFoundException extends HttpException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct(404, 'tenancy.not_found', $context, $hint);
    }
}
