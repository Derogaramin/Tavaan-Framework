<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * PipelineService — فرصت فروش + جابجایی مرحله با Workflow (DOC-096 §3.1).
 */

namespace Modules\Crm\Application\UseCases;

use Modules\Crm\Domain\Entities\Opportunity;
use Modules\Crm\Domain\Enums\OpportunityStage;
use Modules\Crm\Domain\Repositories\OpportunityRepositoryInterface;
use Modules\Crm\Domain\Services\ForecastCalculator;
use Modules\Crm\Infrastructure\Workflow\PipelineWorkflow;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Log;

/**
 * PipelineService — AC-CRM-07: pipeline با Workflow + forecast وزنی درست.
 *
 * هر فرصت یک نمونهٔ `crm-sales-pipeline` می‌گیرد؛ جابجایی مرحله هم در
 * دامنه (Opportunity::moveTo) و هم در workflow بازتاب می‌شود (تاریخچه/
 * visualization موتور). احتمال هر مرحله از config('crm.pipeline.probabilities').
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class PipelineService
{
    /**
     * @param array<string, mixed> $config کلید config('crm')
     */
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunities,
        private readonly PipelineWorkflow $workflow,
        private readonly ForecastCalculator $forecast,
        private readonly array $config = [],
    ) {
    }

    /** ساخت فرصت + شروع نمونهٔ workflow. */
    public function create(
        string $contactId,
        string $title,
        int $valueMinor = 0,
        ?string $leadId = null,
        ?int $ownerId = null,
        ?string $expectedCloseDate = null,
    ): Opportunity {
        $opportunity = Opportunity::create(
            contactId: $contactId,
            title: $title,
            valueMinor: $valueMinor,
            currency: (string) ($this->config['currency'] ?? 'IRR'),
            leadId: $leadId,
            ownerId: $ownerId,
            expectedCloseDate: $expectedCloseDate,
            initialProbability: $this->probabilityOf(OpportunityStage::Prospecting),
        );

        // Workflow — نمونهٔ فرایند (best-effort؛ دامنه بدون آن هم کار می‌کند)
        $instanceId = $this->workflow->start($opportunity->id(), [
            'contact_id' => $contactId,
            'value_minor' => $valueMinor,
            'owner_id' => $ownerId,
        ]);
        if ($instanceId !== null) {
            $opportunity->attachWorkflow($instanceId);
        }

        $this->opportunities->save($opportunity);

        return $opportunity;
    }

    public function revise(Opportunity $opportunity, string $title, ?int $valueMinor = null, ?string $expectedCloseDate = null): Opportunity
    {
        $opportunity->revise($title, $valueMinor, $expectedCloseDate);
        $this->opportunities->save($opportunity);

        return $opportunity;
    }

    /**
     * جابجایی مرحله (§3.1) — دامنه + workflow + رویداد stage_changed.
     */
    public function moveToStage(Opportunity $opportunity, OpportunityStage $target, ?string $lostReason = null): Opportunity
    {
        $from = $opportunity->stage()->value;

        try {
            $opportunity->moveTo($target, $this->probabilityOf($target), $lostReason);
        } catch (\DomainException $e) {
            throw new ValidationException(
                errors: ['stage' => [$e->getMessage()]],
                messageId: 'crm::messages.stage_move_rejected',
            );
        }

        // بازتاب در workflow (best-effort — تاریخچه/visualization)
        if ($opportunity->workflowInstanceId() !== null) {
            $fired = $this->workflow->fireStageChange($opportunity->workflowInstanceId(), $from, $target->value);
            if (!$fired) {
                Log::info('crm.pipeline.workflow_skip', [
                    'opportunity' => $opportunity->id(),
                    'from' => $from,
                    'to' => $target->value,
                ]);
            }
        }

        $this->opportunities->save($opportunity);
        $this->flushEvents($opportunity);

        return $opportunity;
    }

    public function win(Opportunity $opportunity): Opportunity
    {
        return $this->moveToStage($opportunity, OpportunityStage::ClosedWon);
    }

    public function lose(Opportunity $opportunity, string $reason): Opportunity
    {
        if (\trim($reason) === '') {
            throw new ValidationException(
                errors: ['lost_reason' => ['دلیل ناموفق بودن لازم است.']],
                messageId: 'crm::messages.lost_reason_required',
            );
        }

        return $this->moveToStage($opportunity, OpportunityStage::ClosedLost, \trim($reason));
    }

    public function assign(Opportunity $opportunity, ?int $ownerId): Opportunity
    {
        $opportunity->assignTo($ownerId);
        $this->opportunities->save($opportunity);

        return $opportunity;
    }

    /**
     * Forecast وزنی کل pipeline باز (AC-CRM-07).
     *
     * @return array{total_value: int, weighted: int, count: int, by_stage: array<string, array{count: int, value: int, weighted: int}>}
     */
    public function forecast(): array
    {
        $open = $this->opportunities->openOpportunities();

        $rows = [];
        $totalValue = 0;
        foreach ($open as $opportunity) {
            $rows[] = [
                'stage' => $opportunity->stage()->value,
                'value' => $opportunity->valueMinor(),
                'probability' => $opportunity->probability(),
            ];
            $totalValue += $opportunity->valueMinor();
        }

        return [
            'total_value' => $totalValue,
            'weighted' => $this->forecast->weightedTotal($rows),
            'count' => \count($open),
            'by_stage' => $this->forecast->byStage($rows),
        ];
    }

    /** احتمال یک مرحله از config (fallback: enum). */
    public function probabilityOf(OpportunityStage $stage): int
    {
        $map = (array) ($this->config['pipeline']['probabilities'] ?? []);

        return \max(0, \min(100, (int) ($map[$stage->value] ?? $stage->defaultProbability())));
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function flushEvents(Opportunity $opportunity): void
    {
        foreach ($opportunity->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
