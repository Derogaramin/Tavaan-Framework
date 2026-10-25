<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateWorkflow — فرایند صدور گواهی روی موتور workflow (DOC-095 §3.4).
 */

namespace Modules\Lms\Infrastructure\Workflow;

use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Workflow;

/**
 * CertificateWorkflow — تعریف و پیش‌برد فرایند `lms-issue-certificate`:
 *
 * ```
 * requested (start) ──generate──▶ generating ──issue──▶ issued (end)
 *                                     │
 *                                     └──fail──▶ failed (end)
 * ```
 *
 * تعریف به‌صورت lazy (نخستین مصرف) ثبت می‌شود — boot ماژول DB را
 * زودهنگام مصرف نمی‌کند (الگوی policies اعلامی — گزارش M9 گام ۱).
 * نتایج موتور dynamic خوانده می‌شوند (ARCH-003: بدون type-hint موتور).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CertificateWorkflow
{
    private bool $defined = false;

    public function __construct(private readonly string $workflowId = 'lms-issue-certificate')
    {
    }

    /** آیا موتور workflow در دسترس است؟ (بدون آن، صدور مستقیم انجام می‌شود) */
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
     * شروع فرایند صدور برای یک ثبت‌نام.
     *
     * @param array<string, mixed> $variables
     *
     * @return string|null شناسهٔ نمونهٔ فرایند (null = موتور در دسترس نبود)
     */
    public function start(string $enrollmentId, array $variables = []): ?string
    {
        try {
            $this->ensureDefined();

            $instance = Workflow::start(
                $this->workflowId,
                'lms_enrollment',
                $enrollmentId,
                $variables,
            );

            return \is_object($instance) ? (string) ($instance->id ?? '') : null;
        } catch (\Throwable $e) {
            Log::warning('lms.certificate.workflow_start_failed', [
                'enrollment_id' => $enrollmentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** گذار generate — شروع تولید فایل/serial گواهی. */
    public function markGenerating(string $instanceId): void
    {
        $this->fire($instanceId, 'generate');
    }

    /** گذار issue — گواهی صادر و ذخیره شد. */
    public function markIssued(string $instanceId): void
    {
        $this->fire($instanceId, 'issue');
    }

    /** گذار fail — تولید گواهی ناموفق بود. */
    public function markFailed(string $instanceId): void
    {
        $this->fire($instanceId, 'fail');
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
            // گذار تکراری/نامعتبر نباید صدور گواهی را بشکند — فقط ثبت
            Log::warning('lms.certificate.workflow_fire_failed', [
                'instance' => $instanceId,
                'transition' => $transition,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** تعریف فرایند (idempotent در هر فرایند PHP — defineArray نسخه جدید می‌سازد). */
    private function ensureDefined(): void
    {
        if ($this->defined) {
            return;
        }

        Workflow::defineArray([
            'id' => $this->workflowId,
            'states' => [
                ['id' => 'requested', 'type' => 'start'],
                ['id' => 'generating', 'type' => 'normal'],
                ['id' => 'issued', 'type' => 'end'],
                ['id' => 'failed', 'type' => 'end'],
            ],
            'transitions' => [
                ['id' => 'generate', 'from' => 'requested', 'to' => 'generating'],
                ['id' => 'issue', 'from' => 'generating', 'to' => 'issued'],
                ['id' => 'fail', 'from' => 'generating', 'to' => 'failed'],
            ],
        ]);

        $this->defined = true;
    }
}
