<?php

declare(strict_types=1);

namespace Tavaan\Engine\Rbac;

use Tavaan\Core\Contracts\Authorization\ResponseInterface;

/**
 * تَوان | Tavaan — RBAC
 * GateResponse — نتیجهٔ authorize (DOC-014 §2.6: authorize(): Response).
 *
 * @api 1.0
 * @see docs/03-engines/rbac.md
 */
final readonly class GateResponse implements ResponseInterface
{
    private function __construct(
        private bool $allowed,
        private string $msg,
    ) {
    }

    public static function allow(string $message = ''): self
    {
        return new self(true, $message);
    }

    public static function deny(string $message = 'authorization.denied'): self
    {
        return new self(false, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function message(): string
    {
        return $this->msg;
    }
}
