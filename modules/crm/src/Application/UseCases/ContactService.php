<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactService — مدیریت مخاطب + همگام‌سازی (DOC-096 §7).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Contact;
use Modules\Crm\Domain\Events\ContactWasSynced;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ContactService — CRUD مخاطب + upsert همگام‌سازی (shop/web-form).
 *
 * 🔒 national_id در rest رمزنگاری‌شده است (آداپتر)؛ در ویو mask می‌شود.
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class ContactService
{
    /**
     * @param array<string, mixed> $config کلید config('crm')
     */
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت مخاطب.
     *
     * @param array<string, mixed> $input
     */
    public function create(array $input, ?int $ownerId = null): Contact
    {
        $validated = $this->validateInput($input);

        $email = ($validated['email'] ?? null) === null || $validated['email'] === '' ? null : \mb_strtolower((string) $validated['email']);
        if ($email !== null && $this->contacts->findByEmail($email) !== null) {
            throw new ValidationException(
                errors: ['email' => ['مخاطبی با این ایمیل وجود دارد.']],
                messageId: 'crm::messages.email_taken',
            );
        }

        $contact = Contact::create(
            firstName: (string) ($validated['first_name'] ?? ''),
            lastName: (string) ($validated['last_name'] ?? ''),
            email: $email,
            mobile: ($validated['mobile'] ?? null) === '' ? null : ($validated['mobile'] ?? null),
            company: ($validated['company'] ?? null) === '' ? null : ($validated['company'] ?? null),
            title: ($validated['title'] ?? null) === '' ? null : ($validated['title'] ?? null),
            phone: ($validated['phone'] ?? null) === '' ? null : ($validated['phone'] ?? null),
            nationalId: ($validated['national_id'] ?? null) === '' ? null : ($validated['national_id'] ?? null),
            source: (string) ($validated['source'] ?? 'website'),
            ownerId: $ownerId,
            tags: $this->parseTags($validated['tags'] ?? null),
        );

        $this->contacts->save($contact);

        return $contact;
    }

    /**
     * ویرایش مخاطب.
     *
     * @param array<string, mixed> $input
     */
    public function update(Contact $contact, array $input): Contact
    {
        $validated = $this->validateInput($input);

        $email = ($validated['email'] ?? null) === null || $validated['email'] === '' ? null : \mb_strtolower((string) $validated['email']);
        if ($email !== null && $email !== $contact->email()) {
            $existing = $this->contacts->findByEmail($email);
            if ($existing !== null && $existing->id() !== $contact->id()) {
                throw new ValidationException(
                    errors: ['email' => ['مخاطبی با این ایمیل وجود دارد.']],
                    messageId: 'crm::messages.email_taken',
                );
            }
        }

        $contact->revise(
            firstName: (string) ($validated['first_name'] ?? ''),
            lastName: (string) ($validated['last_name'] ?? ''),
            company: ($validated['company'] ?? null) === '' ? null : ($validated['company'] ?? null),
            title: ($validated['title'] ?? null) === '' ? null : ($validated['title'] ?? null),
            email: $email,
            mobile: ($validated['mobile'] ?? null) === '' ? null : ($validated['mobile'] ?? null),
            phone: ($validated['phone'] ?? null) === '' ? null : ($validated['phone'] ?? null),
            nationalId: ($validated['national_id'] ?? null) === '' ? null : ($validated['national_id'] ?? null),
            source: (string) ($validated['source'] ?? $contact->source()),
            tags: \array_key_exists('tags', $validated) ? $this->parseTags($validated['tags']) : null,
        );

        $this->contacts->save($contact);

        return $contact;
    }

    public function assignOwner(Contact $contact, ?int $ownerId): Contact
    {
        $contact->assignOwner($ownerId);
        $this->contacts->save($contact);

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $this->contacts->softDelete($contact->id());
    }

    /**
     * همگام‌سازی از منبع خارجی (DOC-096 §7: shop customer → contact sync).
     *
     * upsert با کلید email (یا user_id) — idempotent.
     *
     * @param array<string, mixed> $attributes name/email/mobile/user_id/…
     */
    public function syncFromExternal(string $source, array $attributes): Contact
    {
        $email = isset($attributes['email']) && $attributes['email'] !== '' ? \mb_strtolower((string) $attributes['email']) : null;
        $userId = isset($attributes['user_id']) ? (int) $attributes['user_id'] : null;

        $contact = null;
        if ($email !== null) {
            $contact = $this->contacts->findByEmail($email);
        }
        if ($contact === null && $userId !== null) {
            $contact = $this->contacts->findByUserId($userId);
        }

        $created = $contact === null;
        if ($contact === null) {
            $contact = Contact::create(
                firstName: (string) ($attributes['first_name'] ?? ($attributes['name'] ?? '')) ?: 'مشتری',
                lastName: (string) ($attributes['last_name'] ?? ''),
                email: $email,
                mobile: isset($attributes['mobile']) ? (string) $attributes['mobile'] : null,
                source: $source,
                userId: $userId,
                customFields: isset($attributes['custom_fields']) && \is_array($attributes['custom_fields']) ? $attributes['custom_fields'] : [],
            );
        } else {
            // به‌روزرسانی ملایم — فقط فیلدهای خالی پر می‌شوند + پیوند کاربر
            if ($userId !== null && $contact->userId() === null) {
                $contact->linkUser($userId);
            }
            $custom = $contact->customFields();
            foreach ((array) ($attributes['custom_fields'] ?? []) as $key => $value) {
                $custom[(string) $key] = $value;
            }
            $contact->revise(
                firstName: $contact->firstName() !== '' ? $contact->firstName() : (string) ($attributes['first_name'] ?? ($attributes['name'] ?? '')),
                lastName: $contact->lastName(),
                email: $contact->email() ?? $email,
                mobile: $contact->mobile() ?? (isset($attributes['mobile']) ? (string) $attributes['mobile'] : null),
                customFields: $custom,
            );
        }

        $this->contacts->save($contact);

        $event = new ContactWasSynced($contact->id(), $source, $created, $userId, (string) ($contact->email() ?? ''));
        Event::dispatch($event->eventName(), $event->toPayload());

        return $contact;
    }

    /** mask کدملی برای نمایش (iranian data masking — README فریمورک). */
    public static function maskNationalId(?string $nationalId): string
    {
        if ($nationalId === null || $nationalId === '') {
            return '—';
        }

        $len = \mb_strlen($nationalId);
        if ($len <= 4) {
            return \str_repeat('*', $len);
        }

        return \str_repeat('*', $len - 4) . \mb_substr($nationalId, -4);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validateInput(array $input): array
    {
        return Validator::validate($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'in:website,referral,campaign,import,shop,support'],
            'tags' => ['nullable'],
        ], attributes: [
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'email' => 'ایمیل',
            'mobile' => 'موبایل',
            'national_id' => 'کد ملی',
        ]);
    }

    /** @return list<string> */
    private function parseTags(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (\is_array($raw)) {
            return \array_values(\array_filter(\array_map(static fn (mixed $t): string => \trim((string) $t), $raw)));
        }

        return \array_values(\array_filter(\array_map('trim', \explode(',', (string) $raw))));
    }
}
