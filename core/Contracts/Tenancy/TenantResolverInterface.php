<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Tenancy;

use Tavaan\Core\Contracts\Http\RequestInterface;

/**
 * تَوان | Tavaan — Contracts
 * TenantResolverInterface — قرارداد استخراج شناسهٔ tenant از درخواست (DOC-028 §2.3).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
interface TenantResolverInterface
{
    /** شناسهٔ tenant (identifier/domain) یا null اگر این resolver نتوانست تشخیص دهد. */
    public function resolve(RequestInterface $request): ?string;
}
