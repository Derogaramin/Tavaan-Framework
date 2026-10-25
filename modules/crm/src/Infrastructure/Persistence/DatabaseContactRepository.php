<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseContactRepository — آداپتر مخاطب (crm_contacts).
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Contact;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Tavaan\Core\Facade\Facades\Crypt;
use Tavaan\Core\Facade\Facades\DB;

/**
 * 🔒 national_id: رمزنگاری AES-GCM در rest (Crypt facade — DOC-096 §2
 * «encrypted cast»). موجودیت همیشه plaintext می‌بیند؛ رمزگشایی خراب
 * (دادهٔ قدیمی/کلید عوض) → null به‌جای شکست (best-effort).
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseContactRepository implements ContactRepositoryInterface
{
    public function __construct(private readonly bool $encryptNationalId = true)
    {
    }

    public function save(Contact $contact): void
    {
        $row = $contact->toRow();
        $row['national_id'] = $this->encryptField($row['national_id']);

        $exists = DB::table('crm_contacts')->where('id', $contact->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('crm_contacts')->where('id', $contact->id())->update($row);
        } else {
            DB::table('crm_contacts')->insert($row);
        }
    }

    public function findById(string $id): ?Contact
    {
        $row = DB::table('crm_contacts')->where('id', $id)->whereNull('deleted_at')->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?Contact
    {
        $row = DB::table('crm_contacts')
            ->where('email', \mb_strtolower(\trim($email)))
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByUserId(int $userId): ?Contact
    {
        $row = DB::table('crm_contacts')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function softDelete(string $id): void
    {
        DB::table('crm_contacts')->where('id', $id)->update([
            'deleted_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('crm_contacts')->whereNull('deleted_at');
        $rows = DB::table('crm_contacts')->whereNull('deleted_at');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['source']) && (string) $filters['source'] !== '') {
                $q->where('source', (string) $filters['source']);
            }
            if (isset($filters['owner_id']) && $filters['owner_id'] !== null) {
                $q->where('owner_id', (int) $filters['owner_id']);
            }
            if (isset($filters['search']) && (string) $filters['search'] !== '') {
                $term = '%' . (string) $filters['search'] . '%';
                $q->where(static function (mixed $w) use ($term): void {
                    $w->where('first_name', 'LIKE', $term)
                        ->orWhere('last_name', 'LIKE', $term)
                        ->orWhere('company', 'LIKE', $term)
                        ->orWhere('email', 'LIKE', $term)
                        ->orWhere('mobile', 'LIKE', $term);
                });
            }
            if (!empty($filters['tags_all'])) {
                foreach ((array) $filters['tags_all'] as $tag) {
                    // tags به‌صورت JSON ذخیره می‌شود — LIKE روی "tag" دقیق
                    $q->where('tags', 'LIKE', '%"' . \str_replace(['"', '%', '_'], '', (string) $tag) . '"%');
                }
            }
            if (($filters['has_email'] ?? null) === true) {
                $q->whereNotNull('email')->where('email', '!=', '');
            }
            if (($filters['has_mobile'] ?? null) === true) {
                $q->whereNotNull('mobile')->where('mobile', '!=', '');
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(fn (array $row): Contact => $this->hydrate($row), $data),
            'total' => $total,
        ];
    }

    public function forCampaign(array $filters, int $limit = 1000): array
    {
        $query = DB::table('crm_contacts')->whereNull('deleted_at');

        if (isset($filters['status']) && (string) $filters['status'] !== '') {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['source']) && (string) $filters['source'] !== '') {
            $query->where('source', (string) $filters['source']);
        }
        if (isset($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }
        if (!empty($filters['tags_all'])) {
            foreach ((array) $filters['tags_all'] as $tag) {
                $query->where('tags', 'LIKE', '%"' . \str_replace(['"', '%', '_'], '', (string) $tag) . '"%');
            }
        }
        if (($filters['has_email'] ?? false) === true) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }
        if (($filters['has_mobile'] ?? false) === true) {
            $query->whereNotNull('mobile')->where('mobile', '!=', '');
        }

        $rows = $query->orderBy('created_at')->limit(\max(1, $limit))->get();

        return \array_map(fn (array $row): Contact => $this->hydrate($row), $rows);
    }

    public function countAll(): int
    {
        return DB::table('crm_contacts')->whereNull('deleted_at')->count();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Contact
    {
        $tags = [];
        $rawTags = $row['tags'] ?? null;
        if (\is_string($rawTags) && $rawTags !== '') {
            $decoded = \json_decode($rawTags, true);
            $tags = \is_array($decoded) ? \array_map('strval', $decoded) : [];
        } elseif (\is_array($rawTags)) {
            $tags = \array_map('strval', $rawTags);
        }

        $custom = [];
        $rawCustom = $row['custom_fields'] ?? null;
        if (\is_string($rawCustom) && $rawCustom !== '') {
            $decoded = \json_decode($rawCustom, true);
            $custom = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($rawCustom)) {
            $custom = $rawCustom;
        }

        return Contact::reconstitute(
            id: (string) ($row['id'] ?? ''),
            firstName: (string) ($row['first_name'] ?? ''),
            lastName: (string) ($row['last_name'] ?? ''),
            company: isset($row['company']) && $row['company'] !== null ? (string) $row['company'] : null,
            title: isset($row['title']) && $row['title'] !== null ? (string) $row['title'] : null,
            email: isset($row['email']) && $row['email'] !== null && $row['email'] !== '' ? (string) $row['email'] : null,
            mobile: isset($row['mobile']) && $row['mobile'] !== null && $row['mobile'] !== '' ? (string) $row['mobile'] : null,
            phone: isset($row['phone']) && $row['phone'] !== null && $row['phone'] !== '' ? (string) $row['phone'] : null,
            nationalId: $this->decryptField(isset($row['national_id']) ? $row['national_id'] : null),
            source: (string) ($row['source'] ?? 'website'),
            ownerId: isset($row['owner_id']) && $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
            userId: isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            status: (string) ($row['status'] ?? 'active'),
            tags: $tags,
            customFields: $custom,
            createdAt: self::date($row['created_at'] ?? null),
            updatedAt: self::date($row['updated_at'] ?? null),
            deletedAt: self::date($row['deleted_at'] ?? null),
        );
    }

    private function encryptField(?string $value): ?string
    {
        if ($value === null || $value === '' || !$this->encryptNationalId) {
            return $value;
        }

        try {
            return Crypt::encrypt($value, 'crm.contact.national_id');
        } catch (\Throwable) {
            // کلید app تنظیم نیست — ذخیرهٔ plaintext بهتر از شکست جریان است؟
            // 🔒 خیر: در نبود رمزنگاری، مقدار ذخیره نمی‌شود (fail-safe).
            return null;
        }
    }

    private function decryptField(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!$this->encryptNationalId) {
            return (string) $value;
        }

        try {
            return Crypt::decrypt((string) $value, 'crm.contact.national_id');
        } catch (\Throwable) {
            return null;   // دادهٔ قدیمی/کلید چرخیده — best-effort
        }
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
