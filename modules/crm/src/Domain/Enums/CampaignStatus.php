<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CampaignStatus — چرخهٔ کمپین (DOC-096 §2).
 */

namespace Modules\Crm\Domain\Enums;

/**
 * draft → scheduled|sending → sent، با انشعاب failed.
 *
 * @api 1.0
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function canTransitionTo(self $target): bool
    {
        if ($target === $this) {
            return false;
        }

        return match ($this) {
            self::Draft => $target === self::Scheduled || $target === self::Sending || $target === self::Failed,
            self::Scheduled => $target === self::Sending || $target === self::Draft || $target === self::Failed,
            self::Sending => $target === self::Sent || $target === self::Failed,
            self::Sent, self::Failed => false,
        };
    }

    public function labelFa(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Scheduled => 'زمان‌بندی‌شده',
            self::Sending => 'در حال ارسال',
            self::Sent => 'ارسال‌شده',
            self::Failed => 'ناموفق',
        };
    }
}
