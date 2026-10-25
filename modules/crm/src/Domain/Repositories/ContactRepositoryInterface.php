<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactRepositoryInterface — پورت مخزن مخاطب.
 */

namespace Modules\Crm\Domain\Repositories;

use Modules\Crm\Domain\Entities\Contact;

/**
 * 🔒 national_id: رمزنگاری/رمزگشایی در آداپتر (encryption at rest — DOC-096 §2).
 *
 * @api 1.0
 */
interface ContactRepositoryInterface
{
    public function save(Contact $contact): void;

    public function findById(string $id): ?Contact;

    public function findByEmail(string $email): ?Contact;

    public function findByUserId(int $userId): ?Contact;

    public function softDelete(string $id): void;

    /**
     * @param array{status?: string|null, source?: string|null, owner_id?: int|null, search?: string, tags_all?: list<string>, has_email?: bool, has_mobile?: bool} $filters
     *
     * @return array{rows: list<Contact>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;

    /**
     * مخاطبان یک کمپین (بدون soft-deleted) — فهرست کامل برای ارسال.
     *
     * @param array<string, mixed> $filters خروجی AudienceResolver
     *
     * @return list<Contact>
     */
    public function forCampaign(array $filters, int $limit = 1000): array;

    public function countAll(): int;
}
