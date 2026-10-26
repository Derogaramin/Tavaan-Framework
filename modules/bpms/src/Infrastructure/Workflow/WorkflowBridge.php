<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * WorkflowBridge — mirror تاریخچه/visualization روی موتور workflow.
 */

namespace Modules\Bpms\Infrastructure\Workflow;

use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Entities\ProcessInstance;
use Modules\Bpms\Domain\Services\WorkflowDefinitionCompiler;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Workflow;

/**
 * WorkflowBridge — «BPMS روی شانهٔ موتور workflow» (DOC-098 §1):
 * تعریف منتشرشده به قالب موتور ترجمه و ثبت می‌شود و هر حرکت نمونه با
 * fire گذار متناظر در `tvn_process_instances` منعکس می‌گردد.
 *
 * 🔒 مرجع صحت اجرا جداول `bpms_*` است (موتور BPMS — §5)؛ mirror فقط
 * تاریخچه/visualization یکپارچه با سایر کیت‌ها فراهم می‌کند
 * (الگوی LMS/CRM/ERP). همه‌جا best-effort: خطای موتور جریان فرایند را
 * نمی‌شکند (log-warning).
 *
 * gatewayها در mirror از نوع normal کامپایل می‌شوند — مسیر واقعی را
 * Bridge با fire صریح حرکت می‌دهد (guardهای expr قابل ترجمه به
 * `can:` موتور نیستند؛ WorkflowDefinitionCompiler را ببینید).
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class WorkflowBridge
{
    private bool $enabled;

    private string $prefix;

    /** @param array<string, mixed> $config کلید config('bpms.workflow_mirror') */
    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->prefix = (string) ($config['prefix'] ?? 'bpms-');
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** نام تعریف mirror برای یک کلید فرایند. */
    public function mirrorName(string $definitionKey): string
    {
        return $this->prefix . $definitionKey;
    }

    /**
     * ثبت/به‌روزرسانی تعریف mirror (هنگام publish/activate).
     *
     * @return bool آیا ثبت شد؟
     */
    public function registerDefinition(ProcessDefinition $definition): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $compiled = WorkflowDefinitionCompiler::compile(
                $this->mirrorName($definition->key()),
                $definition->model(),
                $definition->version(),
            );
            Workflow::defineArray($compiled);

            return true;
        } catch (\Throwable $e) {
            Log::warning('bpms.mirror.define_failed', [
                'key' => $definition->key(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * شروع نمونهٔ mirror — شناسهٔ نمونه برمی‌گردد (null = ناموفق/غیرفعال).
     *
     * @param array<string, mixed> $variables متغیرهای کاربر (بدون رزروها)
     */
    public function startInstance(ProcessInstance $instance, array $variables): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $mirror = Workflow::start(
                $this->mirrorName($instance->definitionKey()),
                'bpms_instance',
                $instance->id(),
                $variables,
            );

            return \is_object($mirror) ? (string) ($mirror->id ?? '') : null;
        } catch (\Throwable $e) {
            Log::warning('bpms.mirror.start_failed', [
                'instance' => $instance->id(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * انعکاس یک حرکت (from→to) با fire گذار کامپایل‌شده.
     *
     * @return bool آیا fire موفق بود؟
     */
    public function fireMove(?string $mirrorInstanceId, string $from, string $to): bool
    {
        if (!$this->enabled || $mirrorInstanceId === null || $mirrorInstanceId === '') {
            return false;
        }

        try {
            $mirror = Workflow::instance($mirrorInstanceId);
            if (!\is_object($mirror) || !\method_exists($mirror, 'fire')) {
                return false;
            }
            $mirror->fire(WorkflowDefinitionCompiler::transitionId($from, $to));

            return true;
        } catch (\Throwable $e) {
            // انتظاری در مسیرهای موازی/دروازه‌ها — mirror لایهٔ تاریخچه است
            Log::warning('bpms.mirror.fire_failed', [
                'instance' => $mirrorInstanceId,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** لغو نمونهٔ mirror (terminate). */
    public function cancelInstance(?string $mirrorInstanceId): bool
    {
        if (!$this->enabled || $mirrorInstanceId === null || $mirrorInstanceId === '') {
            return false;
        }

        try {
            $mirror = Workflow::instance($mirrorInstanceId);
            if (\is_object($mirror) && \method_exists($mirror, 'cancel')) {
                $mirror->cancel();

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('bpms.mirror.cancel_failed', [
                'instance' => $mirrorInstanceId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
