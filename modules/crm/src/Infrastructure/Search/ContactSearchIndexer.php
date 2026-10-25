<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ContactSearchIndexer — ایندکس جست‌وجوی مخاطبان (پنل).
 */

namespace Modules\Crm\Infrastructure\Search;

use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Tavaan\Core\Facade\Facades\Search;

/**
 * ContactSearchIndexer — ایندکس `crm_contacts` (نام/ایمیل/شرکت/موبایل).
 * best-effort: خطای ایندکس جریان اصلی را نمی‌شکند.
 *
 * @api 1.0
 */
final class ContactSearchIndexer
{
    /** @param array<string, mixed> $config کلید config('crm.search') */
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly array $config = [],
    ) {
    }

    public function indexContact(string $contactId): void
    {
        if (!$this->enabled() || $contactId === '') {
            return;
        }

        try {
            $contact = $this->contacts->findById($contactId);
            if ($contact === null || $contact->isDeleted()) {
                $this->removeContact($contactId);

                return;
            }

            Search::index($this->index(), [
                'id' => $contact->id(),
                'name' => $contact->fullName(),
                'company' => (string) $contact->company(),
                'email' => (string) $contact->email(),
                'mobile' => (string) $contact->mobile(),
                'source' => $contact->source(),
                'status' => $contact->status()->value,
            ], $contact->id());
        } catch (\Throwable) {
            // best-effort
        }
    }

    public function removeContact(string $contactId): void
    {
        if (!$this->enabled() || $contactId === '') {
            return;
        }

        try {
            Search::delete($this->index(), $contactId);
        } catch (\Throwable) {
            // سند ممکن است وجود نداشته باشد
        }
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    private function index(): string
    {
        return (string) ($this->config['index'] ?? 'crm_contacts');
    }
}
