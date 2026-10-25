<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * PipelineWorkflow — فرایند pipeline فروش روی موتور workflow (DOC-096 §3.1).
 */

namespace Modules\Crm\Infrastructure\Workflow;

use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Workflow;

/**
 * PipelineWorkflow — تعریف و پیش‌برد فرایند `crm-sales-pipeline` (AC-CRM-07):
 *
 * ```
 * prospecting (start) ──qualify──▶ qualification ──propose──▶ proposal
 *   ──negotiate──▶ negotiation ──win──▶ closed_won (end)
 * هر مرحلهٔ باز ──lose──▶ closed_lost (end)
 * ```
 *
 * تعریف lazy (نخستین مصرف) — boot ماژول DB را زودهنگام مصرف نمی‌کند
 * (الگوی گام ۵/LMS). نتیجهٔ موتور dynamic خوانده می‌شود (ARCH-003).
 * best-effort: در دسترس‌نبودن موتور، pipeline دامنه (Opportunity.moveTo)
 * بدون workflow کار می‌کند.
 *
 * @api 1.0
 * @see modules/crm/README.md
 */
final class PipelineWorkflow
{
    private bool $defined = false;

    public function __construct(private readonly string $workflowId = 'crm-sales-pipeline')
    {
    }

    public function available(): bool
    {
        try {
            $this->ensureDefined();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * شروع فرایند برای یک فرصت.
     *
     * @return string|null شناسهٔ نمونه (null = موتور در دسترس نبود)
     */
    public function start(string $opportunityId, array $variables = []): ?string
    {
        try {
            $this->ensureDefined();

            $instance = Workflow::start($this->workflowId, 'crm_opportunity', $opportunityId, $variables);

            return \is_object($instance) ? (string) ($instance->id ?? '') : null;
        } catch (\Throwable $e) {
            Log::warning('crm.pipeline.workflow_start_failed', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * آتش‌زدن گذار متناسب با جابجایی مرحله.
     *
     * @return bool آیا گذار موفق بود؟
     */
    public function fireStageChange(string $instanceId, string $fromStage, string $toStage): bool
    {
        $transition = $this->transitionFor($fromStage, $toStage);
        if ($transition === null || $instanceId === '') {
            return false;
        }

        try {
            $instance = Workflow::instance($instanceId);
            if (\is_object($instance) && \method_exists($instance, 'fire')) {
                $instance->fire($transition);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('crm.pipeline.workflow_fire_failed', [
                'instance' => $instanceId,
                'transition' => $transition,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** نگاشت (from, to) → شناسهٔ گذار. */
    private function transitionFor(string $from, string $to): ?string
    {
        if ($to === 'closed_lost') {
            return match ($from) {
                'prospecting' => 'lose_p',
                'qualification' => 'lose_q',
                'proposal' => 'lose_pr',
                'negotiation' => 'lose_n',
                default => null,
            };
        }

        if ($to === 'closed_won') {
            return match ($from) {
                'qualification' => 'win_q',
                'proposal' => 'win_pr',
                'negotiation' => 'win_n',
                default => null,
            };
        }

        return match ($from . '>' . $to) {
            'prospecting>qualification' => 'qualify',
            'qualification>proposal' => 'propose',
            'proposal>negotiation' => 'negotiate',
            // یک گام عقب (برگشت)
            'qualification>prospecting' => 'back_q',
            'proposal>qualification' => 'back_pr',
            'negotiation>proposal' => 'back_n',
            default => null,
        };
    }

    private function ensureDefined(): void
    {
        if ($this->defined) {
            return;
        }

        Workflow::defineArray([
            'id' => $this->workflowId,
            'states' => [
                ['id' => 'prospecting', 'type' => 'start'],
                ['id' => 'qualification', 'type' => 'normal'],
                ['id' => 'proposal', 'type' => 'normal'],
                ['id' => 'negotiation', 'type' => 'normal'],
                ['id' => 'closed_won', 'type' => 'end'],
                ['id' => 'closed_lost', 'type' => 'end'],
            ],
            'transitions' => [
                ['id' => 'qualify', 'from' => 'prospecting', 'to' => 'qualification'],
                ['id' => 'propose', 'from' => 'qualification', 'to' => 'proposal'],
                ['id' => 'negotiate', 'from' => 'proposal', 'to' => 'negotiation'],
                ['id' => 'back_q', 'from' => 'qualification', 'to' => 'prospecting'],
                ['id' => 'back_pr', 'from' => 'proposal', 'to' => 'qualification'],
                ['id' => 'back_n', 'from' => 'negotiation', 'to' => 'proposal'],
                ['id' => 'win_q', 'from' => 'qualification', 'to' => 'closed_won'],
                ['id' => 'win_pr', 'from' => 'proposal', 'to' => 'closed_won'],
                ['id' => 'win_n', 'from' => 'negotiation', 'to' => 'closed_won'],
                ['id' => 'lose_p', 'from' => 'prospecting', 'to' => 'closed_lost'],
                ['id' => 'lose_q', 'from' => 'qualification', 'to' => 'closed_lost'],
                ['id' => 'lose_pr', 'from' => 'proposal', 'to' => 'closed_lost'],
                ['id' => 'lose_n', 'from' => 'negotiation', 'to' => 'closed_lost'],
            ],
        ]);

        $this->defined = true;
    }
}
