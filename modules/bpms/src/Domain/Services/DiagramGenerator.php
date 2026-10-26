<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DiagramGenerator — نمودار SVG گراف فرایند (لایهٔ طراحی بصری — §1.1).
 */

namespace Modules\Bpms\Domain\Services;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;

/**
 * DiagramGenerator — رندر سرور-ساید SVG از ProcessModel (بدون JS/بدون
 * وابستگی خارجی — پیش‌نمایش در صفحهٔ تعریف). چیدمان لایه‌ای: BFS از
 * startEvent و طولانی‌ترین-مسیر برای ستون‌ها؛ جریان چپ→راست (قرارداد
 * BPMN). برچسب‌ها XML-escape می‌شوند (🔒 ضد XSS).
 *
 * خروجی Mermaid هم برای درج در مستندات تولید می‌شود.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class DiagramGenerator
{
    private const NODE_W = 150;

    private const NODE_H = 52;

    private const GAP_X = 90;

    private const GAP_Y = 34;

    private const PAD = 30;

    /**
     * تولید SVG.
     *
     * @param list<string> $activeStates وضعیت‌های فعال نمونه (هایلایت توکن)
     */
    public function toSvg(ProcessModel $model, array $activeStates = []): string
    {
        $levels = $this->assignLevels($model);

        /** @var array<int, list<string>> $columns */
        $columns = [];
        foreach ($levels as $id => $level) {
            $columns[$level][] = $id;
        }
        \ksort($columns);

        $positions = [];
        $maxRows = 1;
        foreach ($columns as $level => $ids) {
            $maxRows = \max($maxRows, \count($ids));
            foreach ($ids as $row => $id) {
                $positions[$id] = [
                    'x' => self::PAD + $level * (self::NODE_W + self::GAP_X),
                    'y' => self::PAD + $row * (self::NODE_H + self::GAP_Y),
                ];
            }
        }

        $width = self::PAD * 2 + (\count($columns) > 0 ? \count($columns) * (self::NODE_W + self::GAP_X) - self::GAP_X : self::NODE_W);
        $height = self::PAD * 2 + $maxRows * (self::NODE_H + self::GAP_Y) - self::GAP_Y;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height
            . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="process diagram">'
            . '<defs><marker id="bpms-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">'
            . '<path d="M 0 0 L 10 5 L 0 10 z" fill="#475569"/></marker>'
            . '<marker id="bpms-arrow-timer" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">'
            . '<path d="M 0 0 L 10 5 L 0 10 z" fill="#d97706"/></marker></defs>';

        // یال‌ها (زیر گره‌ها)
        foreach ($model->elements() as $element) {
            $from = $positions[$element->id] ?? null;
            if ($from === null) {
                continue;
            }
            if ($element->next !== null && isset($positions[$element->next])) {
                $svg .= $this->edge($from, $positions[$element->next], '');
            }
            foreach ($element->conditions as $condition) {
                if (!isset($positions[$condition->next])) {
                    continue;
                }
                $label = $condition->default ? 'default' : (string) ($condition->label ?? $condition->expr ?? '');
                $svg .= $this->edge($from, $positions[$condition->next], $label, true);
            }
            foreach ($element->fork as $target) {
                if (isset($positions[$target])) {
                    $svg .= $this->edge($from, $positions[$target], '');
                }
            }
        }

        // تایمرها (خط‌چین نارنجی — حلقه روی همان گره)
        foreach ($model->timers as $timer) {
            if (!isset($positions[$timer->on])) {
                continue;
            }
            $pos = $positions[$timer->on];
            $cx = $pos['x'] + self::NODE_W / 2;
            $cy = $pos['y'] + self::NODE_H;
            $svg .= '<path d="M ' . ($cx - 14) . ' ' . $cy . ' c -16 22, 16 22, 0 2" fill="none" stroke="#d97706" stroke-width="1.5" stroke-dasharray="4 3" marker-end="url(#bpms-arrow-timer)"/>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 30) . '" text-anchor="middle" font-size="10" fill="#b45309">⏱ '
                . $this->esc($timer->after . ' → ' . $timer->action) . '</text>';
        }

        // گره‌ها
        foreach ($model->elements() as $element) {
            $pos = $positions[$element->id] ?? null;
            if ($pos === null) {
                continue;
            }
            $svg .= $this->node($element->type, $element->label(), $pos['x'], $pos['y'], \in_array($element->id, $activeStates, true), $element->assignee);
        }

        return $svg . '</svg>';
    }

    /** نمودار Mermaid (برای مستندات/کپی). */
    public function toMermaid(ProcessModel $model): string
    {
        $out = "flowchart LR\n";
        foreach ($model->elements() as $element) {
            $id = $this->mermaidId($element->id);
            $label = \str_replace('"', '', $element->label());
            $out .= '  ' . $id . match ($element->type) {
                ElementType::StartEvent => '(("' . $label . '"))',
                ElementType::EndEvent => '(("' . $label . '"))',
                ElementType::ExclusiveGateway, ElementType::ParallelGateway => '{' . $label . '}',
                ElementType::ServiceTask => '[[' . $label . ']]',
                default => '[' . $label . ']',
            } . "\n";
        }
        foreach ($model->elements() as $element) {
            if ($element->next !== null) {
                $out .= '  ' . $this->mermaidId($element->id) . ' --> ' . $this->mermaidId($element->next) . "\n";
            }
            foreach ($element->conditions as $condition) {
                $label = $condition->default ? 'default' : (string) ($condition->label ?? $condition->expr ?? '');
                $out .= '  ' . $this->mermaidId($element->id) . ' -->|' . \str_replace('|', '', $label) . '| '
                    . $this->mermaidId($condition->next) . "\n";
            }
            foreach ($element->fork as $target) {
                $out .= '  ' . $this->mermaidId($element->id) . ' ==> ' . $this->mermaidId($target) . "\n";
            }
        }
        foreach ($model->timers as $timer) {
            $out .= '  ' . $this->mermaidId($timer->on) . ' -.->|⏱ ' . $timer->after . ': ' . $timer->action . '| '
                . $this->mermaidId($timer->on) . "\n";
        }

        return $out;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * سطح‌بندی با طولانی‌ترین مسیر (BFS تکراری — گراف ممکن است حلقه داشته باشد).
     *
     * @return array<string, int>
     */
    private function assignLevels(ProcessModel $model): array
    {
        $levels = [$model->startEvent => 0];
        $elements = $model->elements();

        for ($iteration = 0; $iteration < \count($elements) + 1; $iteration++) {
            $changed = false;
            foreach ($elements as $element) {
                if (!isset($levels[$element->id])) {
                    continue;
                }
                foreach ($element->outgoingTargets() as $target) {
                    if (!isset($elements[$target])) {
                        continue;
                    }
                    $candidate = $levels[$element->id] + 1;
                    // حلقه: هرگز سطح را از تعداد عناصر بزرگ‌تر نکن (پایان‌پذیری)
                    if ($candidate > \count($elements)) {
                        continue;
                    }
                    if (!isset($levels[$target]) || $levels[$target] < $candidate) {
                        $levels[$target] = $candidate;
                        $changed = true;
                    }
                }
            }
            if (!$changed) {
                break;
            }
        }

        // عناصر غیرقابل‌رسیدن (validator رد می‌کند؛ اینجا محافظ رندر)
        foreach ($elements as $id => $element) {
            $levels[$id] ??= 0;
        }

        return $levels;
    }

    /**
     * @param array{x: int, y: int} $from
     * @param array{x: int, y: int} $to
     */
    private function edge(array $from, array $to, string $label, bool $condition = false): string
    {
        $x1 = $from['x'] + self::NODE_W;
        $y1 = $from['y'] + self::NODE_H / 2;
        $x2 = $to['x'];
        $y2 = $to['y'] + self::NODE_H / 2;

        $stroke = $condition ? '#0284c7' : '#475569';
        $midX = \intdiv($x1 + $x2, 2);

        // مسیر ارتوگونال ساده (قابل خواندن برای ستون‌های متفاوت)
        $path = $x2 > $x1
            ? 'M ' . $x1 . ' ' . $y1 . ' C ' . $midX . ' ' . $y1 . ', ' . $midX . ' ' . $y2 . ', ' . $x2 . ' ' . $y2
            : 'M ' . $x1 . ' ' . $y1 . ' C ' . ($x1 + 40) . ' ' . $y1 . ', ' . ($x2 - 40) . ' ' . $y2 . ', ' . $x2 . ' ' . $y2;

        $svg = '<path d="' . $path . '" fill="none" stroke="' . $stroke . '" stroke-width="1.6" marker-end="url(#bpms-arrow)"/>';

        if ($label !== '') {
            $lx = \intdiv($x1 + $x2, 2);
            $ly = \intdiv($y1 + $y2, 2) - 6;
            $short = \mb_strlen($label) > 26 ? \mb_substr($label, 0, 25) . '…' : $label;
            $svg .= '<text x="' . $lx . '" y="' . $ly . '" text-anchor="middle" font-size="10" fill="' . $stroke . '">'
                . $this->esc($short) . '</text>';
        }

        return $svg;
    }

    private function node(ElementType $type, string $label, int $x, int $y, bool $active, ?string $assignee): string
    {
        $short = \mb_strlen($label) > 20 ? \mb_substr($label, 0, 19) . '…' : $label;
        $activeStroke = $active ? ' stroke="#16a34a" stroke-width="3"' : '';

        if ($type === ElementType::StartEvent || $type === ElementType::EndEvent) {
            $cx = $x + self::NODE_W / 2;
            $cy = $y + self::NODE_H / 2;
            $r = $type === ElementType::StartEvent ? 22 : 24;
            $fill = $type === ElementType::StartEvent ? '#dcfce7' : '#fee2e2';
            $svg = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $fill . '" stroke="#334155" stroke-width="1.5"'
                . ($type === ElementType::EndEvent ? ' stroke-dasharray="0" ' : '') . $activeStroke . '/>';
            if ($type === ElementType::EndEvent) {
                $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 4) . '" fill="none" stroke="#334155" stroke-width="1.5"/>';
            }
            $svg .= '<text x="' . $cx . '" y="' . ($cy + $r + 14) . '" text-anchor="middle" font-size="11" fill="#0f172a">' . $this->esc($short) . '</text>';

            return $svg;
        }

        if ($type->isGateway()) {
            $cx = $x + self::NODE_W / 2;
            $cy = $y + self::NODE_H / 2;
            $diamond = ($cx) . ',' . ($cy - 26) . ' ' . ($cx + 30) . ',' . $cy . ' ' . $cx . ',' . ($cy + 26) . ' ' . ($cx - 30) . ',' . $cy;
            $svg = '<polygon points="' . $diamond . '" fill="#fef3c7" stroke="#b45309" stroke-width="1.5"' . $activeStroke . '/>';
            $mark = $type === ElementType::ExclusiveGateway ? '×' : '+';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 5) . '" text-anchor="middle" font-size="15" font-weight="bold" fill="#92400e">' . $mark . '</text>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 42) . '" text-anchor="middle" font-size="10" fill="#0f172a">' . $this->esc($short) . '</text>';

            return $svg;
        }

        $fill = $type === ElementType::UserTask ? '#e0f2fe' : '#f1f5f9';
        $stroke = $type === ElementType::UserTask ? '#0369a1' : '#475569';
        $icon = $type === ElementType::UserTask ? '👤 ' : '⚙ ';

        $svg = '<rect x="' . $x . '" y="' . $y . '" width="' . self::NODE_W . '" height="' . self::NODE_H . '" rx="10" fill="'
            . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"' . $activeStroke . '/>';
        $svg .= '<text x="' . ($x + self::NODE_W / 2) . '" y="' . ($y + 21) . '" text-anchor="middle" font-size="11" fill="#0f172a">'
            . $this->esc($icon . $short) . '</text>';
        if ($assignee !== null && $assignee !== '') {
            $svg .= '<text x="' . ($x + self::NODE_W / 2) . '" y="' . ($y + 40) . '" text-anchor="middle" font-size="9" fill="#64748b">'
                . $this->esc($assignee) . '</text>';
        }

        return $svg;
    }

    private function esc(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function mermaidId(string $id): string
    {
        return \preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?? $id;
    }
}
