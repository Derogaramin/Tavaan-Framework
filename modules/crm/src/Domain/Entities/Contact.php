<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Contact — مخاطب/مشتری (DOC-096 §1.2).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\ContactStatus;

/**
 * Contact — ریشهٔ Aggregate مخاطب.
 *
 * 🔒 national_id رمزنگاری‌شده در rest است (انطباق در repository — DOC-096 §2)؛
 * موجودیت همیشه مقدار **رمزگشایی‌شده** را می‌بیند.
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class Contact
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private string $firstName,
        private string $lastName,
        private ?string $company,
        private ?string $title,
        private ?string $email,
        private ?string $mobile,
        private ?string $phone,
        private ?string $nationalId,
        private string $source,
        private ?int $ownerId,
        private ?int $userId,
        private ContactStatus $status,
        /** @var list<string> */
        private array $tags,
        /** @var array<string, mixed> */
        private array $customFields,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /**
     * @param list<string> $tags
     * @param array<string, mixed> $customFields
     */
    public static function create(
        string $firstName,
        string $lastName = '',
        ?string $email = null,
        ?string $mobile = null,
        ?string $company = null,
        ?string $title = null,
        ?string $phone = null,
        ?string $nationalId = null,
        string $source = 'website',
        ?int $ownerId = null,
        ?int $userId = null,
        array $tags = [],
        array $customFields = [],
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($firstName) === '' && \trim($lastName) === '' && ($email === null || $email === '')) {
            throw new \DomainException('مخاطب بدون نام یا ایمیل ساخته نمی‌شود.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            id: \Tavaan\Support\Ulid::generate($now)->toString(),
            firstName: \trim($firstName),
            lastName: \trim($lastName),
            company: self::nullable($company),
            title: self::nullable($title),
            email: $email === null || $email === '' ? null : \mb_strtolower(\trim($email)),
            mobile: self::nullable($mobile),
            phone: self::nullable($phone),
            nationalId: self::nullable($nationalId),
            source: $source,
            ownerId: $ownerId,
            userId: $userId,
            status: ContactStatus::Active,
            tags: self::normalizeTags($tags),
            customFields: $customFields,
            createdAt: $now,
            updatedAt: null,
            deletedAt: null,
        );
    }

    /**
     * @param array<string, mixed> $row مقدار national_id باید **رمزگشایی‌شده** باشد
     * @param list<string> $tags
     * @param array<string, mixed> $customFields
     */
    public static function reconstitute(
        string $id,
        string $firstName,
        string $lastName,
        ?string $company,
        ?string $title,
        ?string $email,
        ?string $mobile,
        ?string $phone,
        ?string $nationalId,
        string $source,
        ?int $ownerId,
        ?int $userId,
        string $status,
        array $tags,
        array $customFields,
        ?\DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $deletedAt,
    ): self {
        return new self(
            $id,
            $firstName,
            $lastName,
            $company,
            $title,
            $email,
            $mobile,
            $phone,
            $nationalId,
            $source,
            $ownerId,
            $userId,
            ContactStatus::tryFromString($status),
            $tags,
            $customFields,
            $createdAt,
            $updatedAt,
            $deletedAt,
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    /**
     * @param list<string> $tags
     * @param array<string, mixed> $customFields
     */
    public function revise(
        string $firstName,
        string $lastName = '',
        ?string $company = null,
        ?string $title = null,
        ?string $email = null,
        ?string $mobile = null,
        ?string $phone = null,
        ?string $nationalId = null,
        ?string $source = null,
        ?array $tags = null,
        ?array $customFields = null,
        bool $clearNationalId = false,
    ): void {
        if (\trim($firstName) === '' && \trim($lastName) === '') {
            throw new \DomainException('نام مخاطب نمی‌تواند خالی باشد.');
        }

        $this->firstName = \trim($firstName);
        $this->lastName = \trim($lastName);
        $this->company = self::nullable($company);
        $this->title = self::nullable($title);
        $this->email = $email === null || $email === '' ? null : \mb_strtolower(\trim($email));
        $this->mobile = self::nullable($mobile);
        $this->phone = self::nullable($phone);
        $this->nationalId = $clearNationalId ? null : self::nullable($nationalId) ?? $this->nationalId;
        $this->source = $source ?? $this->source;
        $this->tags = $tags === null ? $this->tags : self::normalizeTags($tags);
        $this->customFields = $customFields ?? $this->customFields;
        $this->touch();
    }

    public function assignOwner(?int $ownerId): void
    {
        $this->ownerId = $ownerId;
        $this->touch();
    }

    public function linkUser(int $userId): void
    {
        $this->userId = $userId;
        $this->touch();
    }

    public function setStatus(ContactStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function fullName(): string
    {
        return \trim($this->firstName . ' ' . $this->lastName);
    }

    public function company(): ?string
    {
        return $this->company;
    }

    public function hasCompany(): bool
    {
        return $this->company !== null && \trim($this->company) !== '';
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function mobile(): ?string
    {
        return $this->mobile;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function nationalId(): ?string
    {
        return $this->nationalId;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function ownerId(): ?int
    {
        return $this->ownerId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function status(): ContactStatus
    {
        return $this->status;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    public function hasTag(string $tag): bool
    {
        return \in_array($tag, $this->tags, true);
    }

    /** @return array<string, mixed> */
    public function customFields(): array
    {
        return $this->customFields;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * ردیف DB — 🔒 national_id خام است؛ رمزنگاری در repository انجام می‌شود
     * (encryption at rest — موجودیت از رمزنگاری بی‌خبر است).
     *
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company' => $this->company,
            'title' => $this->title,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'national_id' => $this->nationalId,
            'source' => $this->source,
            'owner_id' => $this->ownerId,
            'user_id' => $this->userId,
            'status' => $this->status->value,
            'tags' => (string) \json_encode($this->tags, JSON_UNESCAPED_UNICODE),
            'custom_fields' => (string) \json_encode($this->customFields, JSON_UNESCAPED_UNICODE),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
            'deleted_at' => self::fmt($this->deletedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = \trim($value);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private static function normalizeTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $tag) {
            $tag = \trim((string) $tag);
            if ($tag !== '' && !\in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
