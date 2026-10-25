<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadScorer — امتیازدهی سرنخ (DOC-096 §3.2 — الگوریتم مستند).
 */

namespace Modules\Crm\Domain\Services;

/**
 * LeadScorer — تصمیم خالص (بدون I/O):
 *
 * ```
 * score  = source === 'referral' ? 20 : 5
 *        + hasCompany ? 15 : 0
 *        + value > threshold ? 25 : 10
 *        + engagement (هر فعالیت ۵، سقف ۱۵)
 * cap    = 100
 * ```
 *
 * تطبیق مستند: `engagementScore` (بازکردن ایمیل/بازدید) در این نگارش با
 * تعداد فعالیت‌های ثبت‌شده روی سرنخ/مخاطب تقریب زده می‌شود (call/meeting/
 * email/task) — وب‌هوک‌های تعامل در بک‌لاگ Release 1.0.
 *
 * @api 1.0
 */
final class LeadScorer
{
    /**
     * @param array<string, mixed> $config کلید config('crm.scoring')
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * @param int $activityCount تعداد فعالیت‌های مرتبط (engagement)
     */
    public function score(string $source, bool $hasCompany, int $valueMinor, int $activityCount = 0): int
    {
        $score = 0;

        // منبع — referral ارزشمندترین (DOC-096 §3.2)
        $score += $source === 'referral'
            ? (int) ($this->config['source_referral'] ?? 20)
            : (int) ($this->config['source_default'] ?? 5);

        // شرکت‌دار بودن
        $score += $hasCompany ? (int) ($this->config['has_company'] ?? 15) : 0;

        // ارزش سرنخ
        $threshold = (int) ($this->config['high_value_threshold'] ?? 100_000_000);
        $score += $valueMinor > $threshold
            ? (int) ($this->config['high_value'] ?? 25)
            : (int) ($this->config['low_value'] ?? 10);

        // تعامل
        $score += $this->engagementScore($activityCount);

        return \min((int) ($this->config['cap'] ?? 100), \max(0, $score));
    }

    public function engagementScore(int $activityCount): int
    {
        $per = (int) ($this->config['engagement_per_activity'] ?? 5);
        $max = (int) ($this->config['engagement_max'] ?? 15);

        return \min($max, \max(0, $activityCount) * $per);
    }

    /** برچسب کیفی score — برای صف‌بندی کارشناسان. */
    public function tier(int $score): string
    {
        return match (true) {
            $score >= 70 => 'hot',
            $score >= 40 => 'warm',
            default => 'cold',
        };
    }
}
