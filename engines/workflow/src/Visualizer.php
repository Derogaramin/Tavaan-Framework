<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * Visualizer — تولید نمودار از تعریف فرایند (AC-WF-11): Graphviz DOT و Mermaid.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class Visualizer
{
    /** نمودار Graphviz DOT (قابل رندر با `dot -Tsvg`). */
    public function toDot(ProcessDefinition $definition): string
    {
        $out = 'digraph ' . $this->quote($definition->id) . " {\n";
        $out .= "  rankdir=LR;\n  node [fontname=\"sans-serif\"];\n\n";

        foreach ($definition->states() as $state) {
            $shape = match ($state->type) {
                StateType::Start => 'circle',
                StateType::End => 'doublecircle',
                StateType::ExclusiveGateway, StateType::ParallelGateway => 'diamond',
                StateType::TimerEvent => 'cds',
                StateType::SubProcess => 'box3d',
                default => 'box',
            };
            $label = $state->id;
            if ($state->type === StateType::UserTask && $state->assignee !== null) {
                $label .= '\\n(' . $state->assignee . ')';
            }
            $out .= '  ' . $this->quote($state->id) . ' [shape=' . $shape
                . ', label=' . $this->quote($label) . "];\n";
        }

        $out .= "\n";
        foreach ($definition->transitions() as $transition) {
            $attrs = ['label=' . $this->quote($transition->id)];
            $guardLabels = [];
            foreach ($transition->guards as $guard) {
                if (\is_string($guard)) {
                    $guardLabels[] = $guard;
                }
            }
            if ($guardLabels !== []) {
                $attrs[] = 'tooltip=' . $this->quote(\implode(' & ', $guardLabels));
            }
            $out .= '  ' . $this->quote($transition->from) . ' -> ' . $this->quote($transition->to)
                . ' [' . \implode(', ', $attrs) . "];\n";
        }

        foreach ($definition->timers() as $timer) {
            $out .= '  ' . $this->quote($timer->on) . ' -> ' . $this->quote($timer->to)
                . ' [style=dashed, color=orange, label=' . $this->quote('⏱ ' . $timer->id . ' (' . $timer->after . ')') . "];\n";
        }

        return $out . "}\n";
    }

    /** نمودار Mermaid (قابل رندر در markdown/GitHub). */
    public function toMermaid(ProcessDefinition $definition): string
    {
        $out = "flowchart TD\n";

        foreach ($definition->states() as $state) {
            $id = $this->mermaidId($state->id);
            $label = \str_replace('"', '', $state->id);
            $out .= '  ' . $id . match ($state->type) {
                StateType::Start => '(("' . $label . '"))',
                StateType::End => '((' . $label . '))',
                StateType::ExclusiveGateway, StateType::ParallelGateway => '{' . $label . '}',
                StateType::SubProcess => '[[' . $label . ']]',
                default => '[' . $label . ']',
            } . "\n";
        }

        foreach ($definition->transitions() as $transition) {
            $out .= '  ' . $this->mermaidId($transition->from) . ' -->|'
                . \str_replace('|', '', $transition->id) . '| ' . $this->mermaidId($transition->to) . "\n";
        }

        foreach ($definition->timers() as $timer) {
            $out .= '  ' . $this->mermaidId($timer->on) . ' -.->|' . $timer->id . ' ⏱| '
                . $this->mermaidId($timer->to) . "\n";
        }

        return $out;
    }

    private function quote(string $value): string
    {
        return '"' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function mermaidId(string $id): string
    {
        return \preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?? $id;
    }
}
