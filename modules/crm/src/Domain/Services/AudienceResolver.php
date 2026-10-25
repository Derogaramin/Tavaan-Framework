<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * AudienceResolver — ترجمهٔ audience_query به فیلترهای مخزن (DOC-096 §2).
 */

namespace Modules\Crm\Domain\Services;

/**
 * AudienceResolver — تصمیم خالص: JSON کمپین → آرایهٔ فیلتر استاندارد
 * مخزن مخاطب. کلیدهای پشتیبانی‌شده:
 *
 * - `tags`: [string]     — مخاطب **همهٔ** برچسب‌ها را داشته باشد
 * - `source`: string     — منبع دقیق
 * - `status`: string     — پیش‌فرض active (کمپین به blocked/inactive نمی‌رسد)
 * - `has_email`: bool    — پیش‌فرض true برای type=email
 * - `owner_id`: int      — محدود به مشتریان یک کارشناس
 *
 * @api 1.0
 */
final class AudienceResolver
{
    /**
     * @param array<string, mixed> $audienceQuery
     *
     * @return array<string, mixed> فیلترهای ContactRepository::paginateForCampaign
     */
    public function resolve(array $audienceQuery, string $campaignType = 'email'): array
    {
        $filters = [];

        $tags = [];
        foreach ((array) ($audienceQuery['tags'] ?? []) as $tag) {
            $tag = \trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        if ($tags !== []) {
            $filters['tags_all'] = $tags;
        }

        if (isset($audienceQuery['source']) && \trim((string) $audienceQuery['source']) !== '') {
            $filters['source'] = \trim((string) $audienceQuery['source']);
        }

        $filters['status'] = isset($audienceQuery['status']) && \trim((string) $audienceQuery['status']) !== ''
            ? \trim((string) $audienceQuery['status'])
            : 'active';

        if ($campaignType === 'email') {
            $filters['has_email'] = (bool) ($audienceQuery['has_email'] ?? true);
        } elseif ($campaignType === 'sms') {
            $filters['has_mobile'] = (bool) ($audienceQuery['has_mobile'] ?? true);
        }

        if (isset($audienceQuery['owner_id']) && (int) $audienceQuery['owner_id'] > 0) {
            $filters['owner_id'] = (int) $audienceQuery['owner_id'];
        }

        return $filters;
    }
}
