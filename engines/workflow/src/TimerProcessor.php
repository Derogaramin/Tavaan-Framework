<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Workflow
 * TimerProcessor — پردازش تایمرهای سررسیده (DOC-071 §6 — `tvn workflow:timers` هر دقیقه).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class TimerProcessor
{
    public function __construct(
        private readonly WorkflowManager $manager,
        private readonly int $batchSize = 100,
    ) {
    }

    /**
     * اجرای همهٔ تایمرهای سررسیده.
     *
     * @return int تعداد تایمرهای effect-شده
     */
    public function process(?\DateTimeImmutable $now = null): int
    {
        $now ??= Clock::now();
        $fired = 0;

        foreach ($this->manager->store()->runningInstances($this->batchSize) as $row) {
            $variables = (array) ($row['variables'] ?? []);
            $timers = (array) ($variables['_timers'] ?? []);
            if ($timers === []) {
                continue;
            }

            $due = [];
            foreach ($timers as $timer) {
                if (!\is_array($timer) || !isset($timer['id'], $timer['due'])) {
                    continue;
                }
                try {
                    $dueAt = new \DateTimeImmutable((string) $timer['due']);
                } catch (\Exception) {
                    continue;
                }
                if ($dueAt <= $now) {
                    $due[] = (string) $timer['id'];
                }
            }
            if ($due === []) {
                continue;
            }

            try {
                $instance = $this->manager->instance((string) $row['id']);
            } catch (\Throwable $e) {
                $this->log('warning', 'workflow.timer_load_failed', $e);
                continue;
            }

            foreach ($due as $timerId) {
                try {
                    if ($instance->fireTimer($timerId)) {
                        $fired++;
                    }
                } catch (\Throwable $e) {
                    // 🔒 یک تایمر خراب نباید بقیه را متوقف کند
                    $this->log('error', 'workflow.timer_failed', $e, ['timer' => $timerId, 'instance' => $instance->id]);
                }
            }
        }

        return $fired;
    }

    private function log(string $level, string $messageId, \Throwable $e, array $context = []): void
    {
        $container = WorkflowResolver::container();
        if ($container === null || !$container->bound('log')) {
            return;
        }

        try {
            $logger = $container->make('log');
            if (\is_object($logger) && \method_exists($logger, $level)) {
                $logger->{$level}($messageId, ['error' => $e->getMessage(), ...$context]);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }
}
