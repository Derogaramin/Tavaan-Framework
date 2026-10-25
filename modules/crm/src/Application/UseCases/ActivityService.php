<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ActivityService — ثبت/تکمیل فعالیت (DOC-096 §1.2: call|meeting|email|note|task).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Activity;
use Modules\Crm\Domain\Enums\ActivityType;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Application\UseCases\LeadService;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ActivityService — فعالیت روی contact/lead/opportunity/ticket.
 *
 * هر فعالیت روی سرنخ/مخاطب → recalculate امتیاز سرنخ‌های باز آن مخاطب
 * (engagement در scoring §3.2).
 *
 * @api 1.0
 */
final class ActivityService
{
    public function __construct(
        private readonly ActivityRepositoryInterface $activities,
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadService $leadService,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function log(array $input, int $userId): Activity
    {
        $validated = Validator::validate($input, [
            'activity_type' => ['required', 'in:call,meeting,email,note,task'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'contact_id' => ['nullable', 'string', 'max:26'],
            'lead_id' => ['nullable', 'string', 'max:26'],
            'opportunity_id' => ['nullable', 'string', 'max:26'],
            'ticket_id' => ['nullable', 'string', 'max:26'],
            'due_at' => ['nullable', 'string', 'max:40'],
        ], attributes: [
            'subject' => 'موضوع',
            'activity_type' => 'نوع فعالیت',
        ]);

        $contactId = self::nullableKey($validated['contact_id'] ?? null);
        $leadId = self::nullableKey($validated['lead_id'] ?? null);
        $opportunityId = self::nullableKey($validated['opportunity_id'] ?? null);
        $ticketId = self::nullableKey($validated['ticket_id'] ?? null);

        if ($contactId === null && $leadId === null && $opportunityId === null && $ticketId === null) {
            throw new ValidationException(
                errors: ['subject' => ['فعالیت باید به یک موجودیت متصل باشد.']],
                messageId: 'crm::messages.activity_needs_subject_entity',
            );
        }

        $activity = Activity::log(
            type: ActivityType::tryFromString((string) $validated['activity_type']),
            subject: (string) $validated['subject'],
            userId: $userId,
            body: ($validated['body'] ?? null) === null || $validated['body'] === ''
                ? null
                : Security::sanitize((string) $validated['body'], 'plain'),
            contactId: $contactId,
            leadId: $leadId,
            opportunityId: $opportunityId,
            ticketId: $ticketId,
            dueAt: self::parseDate($validated['due_at'] ?? null),
        );

        $this->activities->save($activity);

        // engagement → بازشماری امتیاز سرنخ‌های بازِ مرتبط
        $this->rescoreLeads($contactId, $leadId);

        return $activity;
    }

    public function complete(Activity $activity): Activity
    {
        $activity->complete();
        $this->activities->save($activity);

        return $activity;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function rescoreLeads(?string $contactId, ?string $leadId): void
    {
        try {
            if ($leadId !== null) {
                $lead = $this->leads->findById($leadId);
                if ($lead !== null && $lead->status()->isOpen()) {
                    $this->leadService->recalculateScore($lead);
                    // ذخیره با score جدید — از راه مخزن leadها
                    $this->saveLead($lead);
                }

                return;
            }

            if ($contactId !== null) {
                foreach ($this->leads->forContact($contactId) as $lead) {
                    if ($lead->status()->isOpen()) {
                        $before = $lead->score();
                        $this->leadService->recalculateScore($lead);
                        if ($lead->score() !== $before) {
                            $this->saveLead($lead);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // scoring مجدد best-effort — جریان اصلی فعالیت نمی‌شکند
        }
    }

    private function saveLead(\Modules\Crm\Domain\Entities\Lead $lead): void
    {
        $this->leads->save($lead);
    }

    private static function nullableKey(mixed $value): ?string
    {
        return $value === null || (string) $value === '' ? null : (string) $value;
    }

    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || (string) $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
