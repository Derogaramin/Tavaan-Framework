<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Authorization;

/**
 * تَوان | Tavaan — Contracts
 * ResponseInterface — پاسخ مجوز (DOC-014 §2.6: authorize(): Response).
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
interface ResponseInterface
{
    public function allowed(): bool;

    public function message(): string;
}
