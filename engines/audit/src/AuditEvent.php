<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit;

/**
 * تَوان | Tavaan — Audit
 * AuditEvent — رویداد ممیزی (value object — DOC-058 §3).
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final readonly class AuditEvent
{
    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function __construct(
        public string $name,
        public string $auditableType,
        public string $auditableId,
        public ?int $userId = null,
        public ?int $tenantId = null,
        public array $oldValues = [],
        public array $newValues = [],
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $traceId = null,
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }

    /** @param array<string, mixed> $newValues */
    public static function create(string $type, string $id, array $newValues, ?int $userId = null): self
    {
        return new self('created', $type, $id, $userId, newValues: $newValues);
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public static function update(string $type, string $id, array $oldValues, array $newValues, ?int $userId = null): self
    {
        return new self('updated', $type, $id, $userId, oldValues: $oldValues, newValues: $newValues);
    }

    /** @param array<string, mixed> $oldValues */
    public static function delete(string $type, string $id, array $oldValues = [], ?int $userId = null): self
    {
        return new self('deleted', $type, $id, $userId, oldValues: $oldValues);
    }

    /** @param array<string, mixed> $oldValues */
    public function withContext(?string $ip = null, ?string $userAgent = null, ?string $traceId = null, ?int $tenantId = null, array $oldValues = []): self
    {
        return new self(
            $this->name,
            $this->auditableType,
            $this->auditableId,
            $this->userId,
            $tenantId ?? $this->tenantId,
            $oldValues !== [] ? $oldValues : $this->oldValues,
            $this->newValues,
            $ip ?? $this->ip,
            $userAgent ?? $this->userAgent,
            $traceId ?? $this->traceId,
            $this->createdAt,
        );
    }
}
