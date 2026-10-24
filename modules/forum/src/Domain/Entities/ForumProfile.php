<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumProfile — پروفایل انجمن کاربر: @username، Karma و ban (DOC-094 §4/§6).
 */

namespace Modules\Forum\Domain\Entities;

/**
 * ForumProfile — یک ردیف به‌ازای هر کاربر (user_id unique).
 *
 * - `username`: هندل یکتا برای @mention و URL پروفایل عمومی.
 * - `karma`: امتیاز Reputation (با رأی/پاسخ برگزیده/فعالیت — ReputationCalculator).
 * - `is_banned`/`banned_until`: ban کاربر توسط admin (DOC-094 §4).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumProfile
{
    private function __construct(
        private ?int $id,
        private readonly int $userId,
        private string $username,
        private ?string $bio,
        private ?string $signature,
        private int $karma,
        private int $threadCount,
        private int $postCount,
        private int $solutionCount,
        private bool $isBanned,
        private ?\DateTimeImmutable $bannedUntil,
        private ?string $banReason,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $userId, string $username): self
    {
        return new self(
            id: null,
            userId: $userId,
            username: $username,
            bio: null,
            signature: null,
            karma: 0,
            threadCount: 0,
            postCount: 0,
            solutionCount: 0,
            isBanned: false,
            bannedUntil: null,
            banReason: null,
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            userId: (int) ($row['user_id'] ?? 0),
            username: (string) ($row['username'] ?? ''),
            bio: isset($row['bio']) ? (string) $row['bio'] : null,
            signature: isset($row['signature']) ? (string) $row['signature'] : null,
            karma: (int) ($row['karma'] ?? 0),
            threadCount: (int) ($row['thread_count'] ?? 0),
            postCount: (int) ($row['post_count'] ?? 0),
            solutionCount: (int) ($row['solution_count'] ?? 0),
            isBanned: (bool) ($row['is_banned'] ?? false),
            bannedUntil: isset($row['banned_until']) ? new \DateTimeImmutable((string) $row['banned_until'], new \DateTimeZone('UTC')) : null,
            banReason: isset($row['ban_reason']) ? (string) $row['ban_reason'] : null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $fmt = 'Y-m-d H:i:s.v';
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'username' => $this->username,
            'bio' => $this->bio,
            'signature' => $this->signature,
            'karma' => $this->karma,
            'thread_count' => $this->threadCount,
            'post_count' => $this->postCount,
            'solution_count' => $this->solutionCount,
            'is_banned' => $this->isBanned ? 1 : 0,
            'banned_until' => $this->bannedUntil?->format($fmt),
            'ban_reason' => $this->banReason,
            'created_at' => $this->createdAt?->format($fmt),
            'updated_at' => $now->format($fmt),
        ];
    }

    public function updateProfile(string $username, ?string $bio, ?string $signature): void
    {
        $this->username = $username;
        $this->bio = $bio;
        $this->signature = $signature;
    }

    /** افزودن/کاهش Karma — هیچ‌گاه از کف تعیین‌شده کمتر نمی‌شود. */
    public function addKarma(int $delta, int $floor = 0): void
    {
        $this->karma = \max($floor, $this->karma + $delta);
    }

    public function registerThread(): void
    {
        $this->threadCount++;
    }

    public function registerPost(): void
    {
        $this->postCount++;
    }

    public function registerSolution(bool $add = true): void
    {
        $this->solutionCount = \max(0, $this->solutionCount + ($add ? 1 : -1));
    }

    public function ban(?\DateTimeImmutable $until, string $reason = ''): void
    {
        $this->isBanned = true;
        $this->bannedUntil = $until;
        $this->banReason = $reason !== '' ? $reason : null;
    }

    public function unban(): void
    {
        $this->isBanned = false;
        $this->bannedUntil = null;
        $this->banReason = null;
    }

    /** آیا همین لحظه ban است؟ (banned_until گذشته → خودکار لغو) */
    public function isCurrentlyBanned(?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isBanned) {
            return false;
        }
        if ($this->bannedUntil === null) {
            return true;   // ban دائم
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->bannedUntil > $now;
    }

    public function applyStats(int $threads, int $posts, int $solutions): void
    {
        $this->threadCount = \max(0, $threads);
        $this->postCount = \max(0, $posts);
        $this->solutionCount = \max(0, $solutions);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id ??= $id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function bio(): ?string
    {
        return $this->bio;
    }

    public function signature(): ?string
    {
        return $this->signature;
    }

    public function karma(): int
    {
        return $this->karma;
    }

    public function threadCount(): int
    {
        return $this->threadCount;
    }

    public function postCount(): int
    {
        return $this->postCount;
    }

    public function solutionCount(): int
    {
        return $this->solutionCount;
    }

    public function banReason(): ?string
    {
        return $this->banReason;
    }

    /** پرچم ban (بدون بررسی انقضا — isCurrentlyBanned را ببینید). */
    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function bannedUntil(): ?\DateTimeImmutable
    {
        return $this->bannedUntil;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
