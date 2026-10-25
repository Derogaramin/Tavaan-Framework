<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * PurchaseApprovalWorkflow — تأیید سلسله‌مراتبی خرید روی موتور workflow (DOC-097 §3).
 */

namespace Modules\Erp\Infrastructure\Workflow;

use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Workflow;

/**
 * PurchaseApprovalWorkflow — فرایند `erp-purchase-approval`:
 *
 * ```
 * submitted (start) ──auto_approve──────────────▶ approved (end)
 *        │──to_finance──▶ finance_review ──finance_approve──▶ approved
 *        │                     │   └──finance_escalate──▶ ceo_review ──ceo_approve──▶ approved
 *        │                     │──reject──────────────────▶ rejected (end)
 *        │                                                (ceo_review ──reject──▶ rejected)
 * ```
 *
 * - `finance_approve` با permission `erp.purchase.approve-finance`
 * - `finance_escalate`/`ceo_approve`/`reject` (CEO) با `erp.purchase.approve-ceo`…
 *   (گذارها permission مستقل دارند — موتور RBAC کاربر جاری را بررسی می‌کند)
 *
 * تعریف lazy (نخستین مصرف) — الگوی CertificateWorkflow در LMS.
 * نتایج موتور dynamic خوانده می‌شوند (ARCH-003: بدون type-hint موتور).
 *
 * @api 1.0
 * @see docs/05-applications/erp.md
 */
final class PurchaseApprovalWorkflow
{
    private bool $defined = false;

    public function __construct(private readonly string $workflowId = 'erp-purchase-approval')
    {
    }

    /** آیا موتور workflow در دسترس است؟ (بدون آن، ماشین‌حالت ماژول تنها مرجع است) */
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
     * شروع فرایند تأیید برای یک سفارش خرید.
     *
     * @param array<string, mixed> $variables
     *
     * @return string|null شناسهٔ نمونهٔ فرایند (null = موتور در دسترس نبود)
     */
    public function start(string $poId, array $variables = []): ?string
    {
        try {
            $this->ensureDefined();

            $instance = Workflow::start($this->workflowId, 'erp_purchase_order', $poId, $variables);

            return \is_object($instance) ? (string) ($instance->id ?? '') : null;
        } catch (\Throwable $e) {
            Log::warning('erp.purchase.workflow_start_failed', [
                'po_id' => $poId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fireAutoApprove(string $instanceId): void
    {
        $this->fire($instanceId, 'auto_approve');
    }

    public function fireToFinance(string $instanceId): void
    {
        $this->fire($instanceId, 'to_finance');
    }

    public function fireFinanceApprove(string $instanceId): void
    {
        $this->fire($instanceId, 'finance_approve');
    }

    public function fireFinanceEscalate(string $instanceId): void
    {
        $this->fire($instanceId, 'finance_escalate');
    }

    public function fireCeoApprove(string $instanceId): void
    {
        $this->fire($instanceId, 'ceo_approve');
    }

    public function fireReject(string $instanceId): void
    {
        $this->fire($instanceId, 'reject');
    }

    public function fireRejectCeo(string $instanceId): void
    {
        $this->fire($instanceId, 'reject_ceo');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function fire(string $instanceId, string $transition): void
    {
        if ($instanceId === '') {
            return;
        }

        try {
            $instance = Workflow::instance($instanceId);
            if (\is_object($instance) && \method_exists($instance, 'fire')) {
                $instance->fire($transition);
            }
        } catch (\Throwable $e) {
            // ماشین‌حالت ماژول مرجع صحت است — workflow برای تاریخچه/visualization
            Log::warning('erp.purchase.workflow_fire_failed', [
                'instance' => $instanceId,
                'transition' => $transition,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensureDefined(): void
    {
        if ($this->defined) {
            return;
        }

        Workflow::defineArray([
            'id' => $this->workflowId,
            'states' => [
                ['id' => 'submitted', 'type' => 'start'],
                ['id' => 'finance_review', 'type' => 'normal'],
                ['id' => 'ceo_review', 'type' => 'normal'],
                ['id' => 'approved', 'type' => 'end'],
                ['id' => 'rejected', 'type' => 'end'],
            ],
            'transitions' => [
                ['id' => 'auto_approve', 'from' => 'submitted', 'to' => 'approved'],
                ['id' => 'to_finance', 'from' => 'submitted', 'to' => 'finance_review'],
                ['id' => 'finance_approve', 'from' => 'finance_review', 'to' => 'approved', 'permission' => 'erp.purchase.approve-finance'],
                ['id' => 'finance_escalate', 'from' => 'finance_review', 'to' => 'ceo_review', 'permission' => 'erp.purchase.approve-finance'],
                ['id' => 'ceo_approve', 'from' => 'ceo_review', 'to' => 'approved', 'permission' => 'erp.purchase.approve-ceo'],
                ['id' => 'reject', 'from' => 'finance_review', 'to' => 'rejected', 'permission' => 'erp.purchase.approve-finance'],
                ['id' => 'reject_ceo', 'from' => 'ceo_review', 'to' => 'rejected', 'permission' => 'erp.purchase.approve-ceo'],
            ],
        ]);

        $this->defined = true;
    }
}
