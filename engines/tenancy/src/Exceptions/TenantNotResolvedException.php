<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantNotResolvedException — عملیات tenant-aware بدون context جاری.
 *
 * 🔒 خطای برنامه‌نویسی/زیرساخت (500) — مثلاً Job بدون tenant_id
 * (TEN-SEC-06: اجرا با tenant قبلی ممنوع).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantNotResolvedException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(array $context = [], ?string $hint = null)
    {
        parent::__construct('tenancy.context_missing', $context, $hint);
    }
}
