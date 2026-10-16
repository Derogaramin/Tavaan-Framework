<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * ProcessBuilder — DSL تعریف فرایند (DOC-071 §3).
 *
 * ### مثال
 * ```php
 * Workflow::define('order-approval', function (ProcessBuilder $p): void {
 *     $p->start('draft');
 *     $p->state('pending_review', type: StateType::UserTask)->assignee('role:shop-manager');
 *     $p->state('rejected', type: StateType::End);
 *     $p->transition('submit', from: 'draft', to: 'pending_review')
 *         ->guard(fn (ProcessContext $c) => $c->get('amount') > 0);
 *     $p->timerEvent('auto_reject', after: '3 days', on: 'pending_review', to: 'rejected');
 * });
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessBuilder
{
    /** @var array<string, StateDefinition> */
    private array $states = [];

    /** @var array<string, TransitionDefinition> */
    private array $transitions = [];

    /** @var array<string, TimerDefinition> */
    private array $timers = [];

    /** @internal — توسط WorkflowManager ساخته می‌شود */
    public function __construct(public readonly string $processId)
    {
    }

    /** تعریف state شروع (فقط یکی). */
    public function start(string $id): StateFluent
    {
        return $this->state($id, StateType::Start);
    }

    /** تعریف state عادی/کار انسانی/پایان/زیرفرایند. */
    public function state(string $id, StateType $type = StateType::Normal, ?string $process = null): StateFluent
    {
        $this->states[$id] = new StateDefinition(id: $id, type: $type, process: $process);

        return new StateFluent($this, $id);
    }

    /** دروازهٔ انحصاری (انتخاب اولین گذار با guard موفق). */
    public function exclusiveGateway(string $id): StateFluent
    {
        return $this->state($id, StateType::ExclusiveGateway);
    }

    /** دروازهٔ موازی — چند خروجی = fork، چند ورودی = join. */
    public function parallelGateway(string $id): StateFluent
    {
        return $this->state($id, StateType::ParallelGateway);
    }

    /** رویداد زمان‌دار: پس از `after` روی state `on` → انتقال به `to`. */
    public function timerEvent(string $id, string $after, string $on, string $to): self
    {
        $this->timers[$id] = new TimerDefinition($id, $after, $on, $to);

        return $this;
    }

    /** گذار بین دو state. */
    public function transition(string $id, string $from, string $to): TransitionFluent
    {
        $this->transitions[$id] = new TransitionDefinition($id, $from, $to);

        return new TransitionFluent($this, $id);
    }

    /** @internal */
    public function replaceState(string $id, StateDefinition $state): void
    {
        $this->states[$id] = $state;
    }

    /** @internal */
    public function replaceTransition(string $id, TransitionDefinition $transition): void
    {
        $this->transitions[$id] = $transition;
    }

    /** @internal */
    public function stateDefinition(string $id): ?StateDefinition
    {
        return $this->states[$id] ?? null;
    }

    /** @internal */
    public function transitionDefinition(string $id): ?TransitionDefinition
    {
        return $this->transitions[$id] ?? null;
    }

    /**
     * ساخت ProcessDefinition نهایی (همراه با validate کامل).
     *
     * @throws Exceptions\ProcessDefinitionException
     */
    public function build(int $version = 1): ProcessDefinition
    {
        $definition = ProcessDefinition::blank($this->processId, $version);
        foreach ($this->states as $state) {
            $definition->addState($state);
        }
        foreach ($this->transitions as $transition) {
            $definition->addTransition($transition);
        }
        foreach ($this->timers as $timer) {
            $definition->addTimer($timer);
        }
        $definition->validate();

        return $definition;
    }
}
