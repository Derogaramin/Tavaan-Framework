<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * Vote — DTO یک رأی (ثبت‌شده در poll_votes).
 */

namespace Modules\Poll\Domain\Entities;

/**
 * Vote — تغییرناپذیر؛ برای هر گزینهٔ انتخاب‌شده یک ردیف (رأی چندگانه = چند ردیف).
 *
 * 🔒 حریم خصوصی: IP خام ذخیره نمی‌شود — فقط `ip_hash` (sha256 با نمک app.key).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final readonly class Vote
{
    public function __construct(
        public string $id,
        public string $pollId,
        public int $optionIndex,
        public ?int $userId,
        public ?string $ipHash,
        public ?\DateTimeImmutable $createdAt,
    ) {
    }

    /** ساخت رأیٔ تازه (شناسهٔ ULID). */
    public static function cast(string $pollId, int $optionIndex, ?int $userId, ?string $ipHash): self
    {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            pollId: $pollId,
            optionIndex: $optionIndex,
            userId: $userId,
            ipHash: $ipHash,
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $createdAt = null;
        if (isset($row['created_at']) && $row['created_at'] !== null && $row['created_at'] !== '') {
            try {
                $createdAt = new \DateTimeImmutable((string) $row['created_at']);
            } catch (\Throwable) {
                $createdAt = null;
            }
        }

        return new self(
            id: (string) ($row['id'] ?? ''),
            pollId: (string) ($row['poll_id'] ?? ''),
            optionIndex: (int) ($row['option_index'] ?? 0),
            userId: isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            ipHash: isset($row['ip_hash']) && $row['ip_hash'] !== null ? (string) $row['ip_hash'] : null,
            createdAt: $createdAt,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'poll_id' => $this->pollId,
            'option_index' => $this->optionIndex,
            'user_id' => $this->userId,
            'ip_hash' => $this->ipHash,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s.v'),
        ];
    }
}
