<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactWasSynced — همگام‌سازی مخاطب از منبع خارجی (DOC-096 §7: shop).
 */

namespace Modules\Crm\Domain\Events;

/**
 * @api 1.0
 */
final readonly class ContactWasSynced
{
    public function __construct(
        public string $contactId,
        public string $source,
        public bool $created,
        public ?int $userId = null,
        public string $email = '',
    ) {
    }

    public function eventName(): string
    {
        return 'crm.contact.synced';
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'contact_id' => $this->contactId,
            'source' => $this->source,
            'created' => $this->created,
            'user_id' => $this->userId,
            'email' => $this->email,
        ];
    }
}
