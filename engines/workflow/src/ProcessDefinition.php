<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Engine\Workflow\Exceptions\ProcessDefinitionException;

/**
 * تَوان | Tavaan — Workflow
 * ProcessDefinition — قالب فرایند (states + transitions + timers) — تغییرناپذیر.
 *
 * 🔒 ساخت فقط از راه `ProcessBuilder` یا `fromArray` (همراه با validate کامل).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessDefinition
{
    /** @var array<string, StateDefinition> stateId → state */
    private array $states = [];

    /** @var array<string, TransitionDefinition> transitionId → transition */
    private array $transitions = [];

    /** @var array<string, TimerDefinition> timerId → timer */
    private array $timers = [];

    /** @var array<string, list<TransitionDefinition>> stateId → گذارهای خروجی (ترتیب = ترتیب تعریف) */
    private array $outgoing = [];

    /** @var array<string, list<TransitionDefinition>> stateId → گذارهای ورودی */
    private array $incoming = [];

    private ?string $startStateId = null;

    private function __construct(
        public readonly string $id,
        public readonly int $version = 1,
    ) {
    }

    /**
     * ساخت از آرایهٔ JSON (DOC-071 §3.1 — برای Designer/ذخیره در DB).
     *
     * @param array<string, mixed> $data
     *
     * @throws ProcessDefinitionException ساختار نامعتبر
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');
        $definition = new self($id, (int) ($data['version'] ?? 1));

        foreach ((array) ($data['states'] ?? []) as $rawState) {
            if (!\is_array($rawState)) {
                continue;
            }
            $state = StateDefinition::fromArray($rawState);
            $definition->addState($state);
        }

        foreach ((array) ($data['transitions'] ?? []) as $rawTransition) {
            if (!\is_array($rawTransition)) {
                continue;
            }
            $definition->addTransition(TransitionDefinition::fromArray($rawTransition));
        }

        foreach ((array) ($data['timers'] ?? []) as $rawTimer) {
            if (!\is_array($rawTimer)) {
                continue;
            }
            $definition->addTimer(TimerDefinition::fromArray($rawTimer));
        }

        $definition->validate();

        return $definition;
    }

    /**
     * ساخت نمونهٔ خام برای ProcessBuilder (validate در `build()`).
     *
     * @internal
     */
    public static function blank(string $id, int $version = 1): self
    {
        return new self($id, $version);
    }

    /** @internal — فقط ProcessBuilder */
    public function addState(StateDefinition $state): void
    {
        if (\preg_match('/^[A-Za-z0-9_.\-:\/]+$/', $state->id) !== 1) {
            throw ProcessDefinitionException::invalidId($this->id, $state->id);
        }
        if (isset($this->states[$state->id])) {
            throw ProcessDefinitionException::duplicateState($this->id, $state->id);
        }

        $this->states[$state->id] = $state;
        if ($state->type === StateType::Start) {
            $this->startStateId = $state->id;
        }
    }

    /** @internal — فقط ProcessBuilder */
    public function addTransition(TransitionDefinition $transition): void
    {
        if (\preg_match('/^[A-Za-z0-9_.\-:\/]+$/', $transition->id) !== 1) {
            throw ProcessDefinitionException::invalidId($this->id, $transition->id);
        }
        if (isset($this->transitions[$transition->id])) {
            throw ProcessDefinitionException::duplicateTransition($this->id, $transition->id);
        }

        $this->transitions[$transition->id] = $transition;
        $this->outgoing[$transition->from][] = $transition;
        $this->incoming[$transition->to][] = $transition;
    }

    /** @internal — فقط ProcessBuilder */
    public function addTimer(TimerDefinition $timer): void
    {
        $this->timers[$timer->id] = $timer;
    }

    /**
     * اعتبارسنجی ساختاری (DOC-071 §9 T-01).
     *
     * @throws ProcessDefinitionException
     */
    public function validate(): void
    {
        if ($this->startStateId === null) {
            throw ProcessDefinitionException::missingStart($this->id);
        }

        foreach ($this->transitions as $transition) {
            if (!isset($this->states[$transition->from])) {
                throw ProcessDefinitionException::unknownState($this->id, $transition->from, 'transition:' . $transition->id . '.from');
            }
            if (!isset($this->states[$transition->to])) {
                throw ProcessDefinitionException::unknownState($this->id, $transition->to, 'transition:' . $transition->id . '.to');
            }
        }

        foreach ($this->timers as $timer) {
            if (!isset($this->states[$timer->on])) {
                throw ProcessDefinitionException::unknownState($this->id, $timer->on, 'timer:' . $timer->id . '.on');
            }
            if (!isset($this->states[$timer->to])) {
                throw ProcessDefinitionException::unknownState($this->id, $timer->to, 'timer:' . $timer->id . '.to');
            }
            // پارس‌پذیری مدت (خطای زودهنگام)
            Duration::parse($timer->after);
        }

        foreach ($this->states as $state) {
            if ($state->type === StateType::SubProcess && ($state->process === null || $state->process === '')) {
                throw ProcessDefinitionException::missingProcess($this->id, $state->id);
            }
            if ($state->type === StateType::Start || $state->type === StateType::End) {
                continue;
            }
            $in = \count($this->incoming[$state->id] ?? []);
            $out = \count($this->outgoing[$state->id] ?? []);
            if ($in === 0 && $out === 0) {
                throw ProcessDefinitionException::orphanTransition($this->id, $state->id);
            }
        }
    }

    public function state(string $id): ?StateDefinition
    {
        return $this->states[$id] ?? null;
    }

    /** @return array<string, StateDefinition> */
    public function states(): array
    {
        return $this->states;
    }

    public function transition(string $id): ?TransitionDefinition
    {
        return $this->transitions[$id] ?? null;
    }

    /** @return array<string, TransitionDefinition> */
    public function transitions(): array
    {
        return $this->transitions;
    }

    /** @return list<TransitionDefinition> گذارهای خروجی یک state (ترتیب تعریف) */
    public function outgoing(string $stateId): array
    {
        return $this->outgoing[$stateId] ?? [];
    }

    /** @return list<TransitionDefinition> گذارهای ورودی یک state */
    public function incoming(string $stateId): array
    {
        return $this->incoming[$stateId] ?? [];
    }

    public function startState(): StateDefinition
    {
        /** @var StateDefinition */
        return $this->states[(string) $this->startStateId];
    }

    public function startStateId(): string
    {
        return (string) $this->startStateId;
    }

    /** @return array<string, TimerDefinition> */
    public function timers(): array
    {
        return $this->timers;
    }

    public function timer(string $id): ?TimerDefinition
    {
        return $this->timers[$id] ?? null;
    }

    /** تایمرهایی که روی یک state فعال می‌شوند. @return list<TimerDefinition> */
    public function timersFor(string $stateId): array
    {
        return \array_values(\array_filter(
            $this->timers,
            static fn (TimerDefinition $t): bool => $t->on === $stateId,
        ));
    }

    public function isGateway(string $stateId): bool
    {
        return ($this->states[$stateId] ?? null)?->type->isGateway() ?? false;
    }

    public function isEnd(string $stateId): bool
    {
        return ($this->states[$stateId] ?? null)?->type === StateType::End;
    }

    public function isUserTask(string $stateId): bool
    {
        return ($this->states[$stateId] ?? null)?->type === StateType::UserTask;
    }

    /** نسخهٔ جدید با همان محتوا (برای version++ در WorkflowManager). */
    public function withVersion(int $version): self
    {
        $copy = new self($this->id, $version);
        foreach ($this->states as $state) {
            $copy->addState($state);
        }
        foreach ($this->transitions as $transition) {
            $copy->addTransition($transition);
        }
        foreach ($this->timers as $timer) {
            $copy->addTimer($timer);
        }

        return $copy;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $states = [];
        foreach ($this->states as $state) {
            $states[] = $state->toArray();
        }
        $transitions = [];
        foreach ($this->transitions as $transition) {
            $transitions[] = $transition->toArray();
        }
        $timers = [];
        foreach ($this->timers as $timer) {
            $timers[] = $timer->toArray();
        }

        return [
            'id' => $this->id,
            'version' => $this->version,
            'states' => $states,
            'transitions' => $transitions,
            'timers' => $timers,
        ];
    }
}
