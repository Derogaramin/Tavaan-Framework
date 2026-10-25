<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * LeadService — سرنخ: ساخت/qualify/تبدیل/امتیازدهی (DOC-096 §3.1-3.2).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Lead;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Modules\Crm\Domain\Repositories\LeadRepositoryInterface;
use Modules\Crm\Domain\Services\LeadScorer;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * LeadService — جریان §3.1:
 *
 * ```
 * Lead (new) → [scoring خودکار §3.2] → qualify → convertTo → Opportunity
 * ```
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class LeadService
{
    /**
     * @param array<string, mixed> $config کلید config('crm')
     */
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly ContactRepositoryInterface $contacts,
        private readonly ActivityRepositoryInterface $activities,
        private readonly LeadScorer $scorer,
        private readonly PipelineService $pipeline,
        private readonly array $config = [],
    ) {
    }

    /**
     * ساخت سرنخ + scoring خودکار (رویداد crm.lead.created → listenerها).
     *
     * @param array<string, mixed> $input
     */
    public function create(string $contactId, array $input, ?int $ownerId = null): Lead
    {
        $contact = $this->contacts->findById($contactId);
        if ($contact === null) {
            throw new ValidationException(
                errors: ['contact' => ['مخاطب یافت نشد.']],
                messageId: 'crm::messages.contact_not_found',
            );
        }

        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
        ], attributes: ['title' => 'عنوان سرنخ', 'value' => 'ارزش']);

        $lead = Lead::create(
            contactId: $contactId,
            title: (string) $validated['title'],
            valueMinor: (int) ($validated['value'] ?? 0),
            currency: (string) ($this->config['currency'] ?? 'IRR'),
            ownerId: $ownerId,
            source: $contact->source(),
        );

        // scoring خودکار (§3.2) — engagement = فعالیت‌های مخاطب
        $lead->applyScore($this->scoreOf($lead, $contact->hasCompany(), $contact->source()));

        $this->leads->save($lead);
        $this->flushEvents($lead);

        return $lead;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(Lead $lead, array $input): Lead
    {
        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
        ], attributes: ['title' => 'عنوان سرنخ']);

        $lead->revise(
            title: (string) $validated['title'],
            valueMinor: ($validated['value'] ?? null) === null ? null : (int) $validated['value'],
        );

        $this->recalculateScore($lead);
        $this->leads->save($lead);

        return $lead;
    }

    /** تأیید صلاحیت (§3.1: qualify). */
    public function qualify(Lead $lead): Lead
    {
        $lead->qualify();
        $this->leads->save($lead);
        $this->flushEvents($lead);

        return $lead;
    }

    public function disqualify(Lead $lead): Lead
    {
        $lead->disqualify();
        $this->leads->save($lead);

        return $lead;
    }

    public function markLost(Lead $lead, string $reason = ''): Lead
    {
        $lead->markLost($reason);
        $this->leads->save($lead);
        $this->flushEvents($lead);

        return $lead;
    }

    /**
     * تبدیل به فرصت فروش (§3.1) — Opportunity در Prospecting + workflow.
     *
     * @param array<string, mixed> $input عنوان/ارزش/تاریخ انتظار (اختیاری — پیش‌فرض از سرنخ)
     */
    public function convert(Lead $lead, array $input = [], ?int $actorId = null): array
    {
        $contact = $this->contacts->findById($lead->contactId());
        if ($contact === null) {
            throw new ValidationException(
                errors: ['contact' => ['مخاطب سرنخ یافت نشد.']],
                messageId: 'crm::messages.contact_not_found',
            );
        }

        $opportunity = $this->pipeline->create(
            contactId: $lead->contactId(),
            title: (string) ($input['title'] ?? $lead->title()),
            valueMinor: isset($input['value']) ? (int) $input['value'] : $lead->valueMinor(),
            leadId: $lead->id(),
            ownerId: $lead->ownerId() ?? $actorId,
            expectedCloseDate: isset($input['expected_close_date']) && (string) $input['expected_close_date'] !== ''
                ? (string) $input['expected_close_date']
                : null,
        );

        $lead->convertTo($opportunity->id());
        $this->leads->save($lead);
        $this->flushEvents($lead);   // → crm.lead.converted

        return ['lead' => $lead, 'opportunity' => $opportunity];
    }

    public function assign(Lead $lead, int $ownerId): Lead
    {
        $lead->assignTo($ownerId);
        $this->leads->save($lead);

        return $lead;
    }

    /** بازشماری امتیاز یک سرنخ (پس از فعالیت جدید / دستی). */
    public function recalculateScore(Lead $lead): int
    {
        $contact = $this->contacts->findById($lead->contactId());
        $score = $this->scoreOf($lead, $contact?->hasCompany() ?? false, $contact?->source() ?? $lead->source());
        $lead->applyScore($score);

        return $score;
    }

    /** امتیاز همهٔ سرنخ‌های باز (نگهداری — `crm:recalculate-scores`). */
    public function recalculateAll(): int
    {
        $count = 0;
        foreach ($this->leads->all() as $lead) {
            if (!$lead->status()->isOpen()) {
                continue;
            }
            $before = $lead->score();
            $this->recalculateScore($lead);
            if ($lead->score() !== $before) {
                $this->leads->save($lead);
                $count++;
            }
        }

        return $count;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function scoreOf(Lead $lead, bool $hasCompany, string $source): int
    {
        $engagement = $this->activities->countFor($lead->contactId(), $lead->id());

        return $this->scorer->score($source, $hasCompany, $lead->valueMinor(), $engagement);
    }

    private function flushEvents(Lead $lead): void
    {
        foreach ($lead->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
