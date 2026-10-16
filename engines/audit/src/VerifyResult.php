<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

/**
 * تَوان | Tavaan — Audit
 * VerifyResult — نتیجهٔ بازبینی زنجیرهٔ هش (DOC-058 §3).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final readonly class VerifyResult
{
    private function __construct(
        public bool $intact,
        public int $checked,
        public ?string $tamperedAt = null,
        public ?string $reason = null,
    ) {
    }

    public static function intact(int $checked): self
    {
        return new self(true, $checked);
    }

    public static function tampered(string $auditId, string $reason, int $checked): self
    {
        return new self(false, $checked, $auditId, $reason);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intact' => $this->intact,
            'checked' => $this->checked,
            'tampered_at' => $this->tamperedAt,
            'reason' => $this->reason,
        ];
    }
}
