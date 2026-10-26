<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * TimerService — تایمر/escalation کارهای سررسیدگذشته (DOC-098 §9 — AC-BPMS-13).
 */

namespace Modules\Bpms\Application\UseCases;

use Modules\Bpms\Domain\Entities\ProcessDefinition;
use Modules\Bpms\Domain\Entities\Task;
use Modules\Bpms\Domain\Enums\AssigneeType;
use Modules\Bpms\Domain\Events\TaskWasEscalated;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Modules\Bpms\Domain\Repositories\InstanceRepositoryInterface;
use Modules\Bpms\Domain\Repositories\TaskRepositoryInterface;
use Modules\Bpms\Domain\ValueObjects\TimerSpec;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Support\Time\Clock;

/**
 * TimerService — `bpms:check-timers` (schedule هر دقیقه):
 * کارهای بازِ سررسیدگذشته و escalate-نشده پیدا می‌شوند و تایمر تعریف
 * (`{"on":state,"after":"P2D","action":"escalate"}`) اجرا می‌شود:
 *
 * - اکشن `escalate` (داخلی): reassign به نقش پیکربندی‌شده + رویداد
 *   `bpms.task.escalated` + اعلان
 * - سایر اکشن‌ها: ActionRegistry (ثبت‌شده — §6)؛ پس از اجرا مهر
 *   escalation می‌خورد تا idempotent بماند
 *
 * 🔒 idempotent: escalated_at مهر می‌خورد و کارِ مهرشده دوباره پردازش
 * نمی‌شود (overdueOpen فقط escalate-نشده‌ها را برمی‌گرداند).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class TimerService
{
    /** @param array<string, mixed> $config کلید config('bpms') */
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly InstanceRepositoryInterface $instances,
        private readonly DefinitionRepositoryInterface $definitions,
        private readonly ActionRegistry $actions,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    /**
     * پردازش یک دسته از کارهای سررسیدگذشته.
     *
     * @return int تعداد کارهای پردازش‌شده
     */
    public function processDue(): int
    {
        if (!(bool) ($this->config['timers']['enabled'] ?? true)) {
            return 0;
        }

        $now = Clock::now();
        $batch = (int) ($this->config['timers']['batch_size'] ?? 100);
        $processed = 0;

        foreach ($this->tasks->overdueOpen($now->format('Y-m-d H:i:s.v'), $batch) as $task) {
            if ($this->processTask($task, $now)) {
                $processed++;
            }
        }

        return $processed;
    }

    /** اجرای تایمر یک کار مشخص (تست/دستی). */
    public function processTask(Task $task, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= Clock::now();

        if ($task->isEscalated() || !$task->status()->isOpen() || !$task->isOverdue($now)) {
            return false;   // idempotent
        }

        $instance = $this->instances->findById($task->instanceId());
        if ($instance === null || !$instance->isRunning()) {
            return false;
        }

        $definition = $this->definitions->findById($instance->definitionId());
        if ($definition === null) {
            return false;
        }

        // تایمرهای متناظر با state کار؛ بدون تایمر صریح → escalate پیش‌فرض
        // (عنصر `due` داشته وگرنه سررسیدی هم نبود)
        $timers = $definition->model()->timersFor($task->stateId());
        if ($timers === []) {
            $timers = [new TimerSpec($task->stateId(), 'P0D', 'escalate')];
        }

        $processed = false;
        foreach ($timers as $timer) {
            $processed = $this->runTimerAction($task, $definition, $timer->action, $now) || $processed;
        }

        return $processed;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function runTimerAction(Task $task, ProcessDefinition $definition, string $action, \DateTimeImmutable $now): bool
    {
        $instance = $this->instances->findById($task->instanceId());
        if ($instance === null) {
            return false;
        }

        // اکشن سفارشی ثبت‌شده (§6) — اجرا + مهر idempotency
        if ($action !== 'escalate' && $this->actions->has($action)) {
            $context = new ProcessContext($instance, $this->container, task: $task);
            $this->actions->execute($action, $context);

            // تغییرات متغیرها (ctx->set) پایدار شود
            $this->instances->update($instance);

            $fresh = $this->tasks->findById($task->id());
            if ($fresh !== null && $fresh->status()->isOpen()) {
                $fresh->stampEscalation($now->format('Y-m-d H:i:s.v'), 'action:' . $action);
                $this->tasks->update($fresh);
            }
            $this->dispatchEscalated($task, 'action:' . $action, $action);

            return true;
        }

        // escalate داخلی (یا fallback اکشن ناشناخته)
        return $this->escalate($task, $now);
    }

    /** escalation داخلی: reassign به نقش config + رویداد + مهر idempotency. */
    private function escalate(Task $task, \DateTimeImmutable $now): bool
    {
        $role = (string) ($this->config['timers']['escalate_role'] ?? 'process-admin');

        $task->escalate(AssigneeType::Role, $role, $now->format('Y-m-d H:i:s.v'));
        $this->tasks->update($task);
        $this->dispatchEscalated($task, $role, 'escalate');

        return true;
    }

    private function dispatchEscalated(Task $task, string $escalatedTo, string $action): void
    {
        $event = new TaskWasEscalated(
            $task->id(),
            $task->instanceId(),
            $task->stateId(),
            $task->title(),
            $escalatedTo,
            $action,
        );
        Event::dispatch($event->eventName(), $event->toPayload());
    }
}
