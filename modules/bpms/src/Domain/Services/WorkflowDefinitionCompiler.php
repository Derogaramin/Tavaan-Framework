<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * WorkflowDefinitionCompiler — ترجمهٔ مدل BPMS به قالب موتور workflow.
 */

namespace Modules\Bpms\Domain\Services;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;

/**
 * WorkflowDefinitionCompiler — مدل عنصر-محور BPMS (DOC-098 §3) را به
 * قالب state/transition موتور workflow (DOC-071 §3.1) ترجمه می‌کند.
 *
 * کاربرد: **mirror تاریخچه/visualization** در موتور رسمی (الگوی
 * LMS/CRM/ERP — مرجع صحت جداول `bpms_*` است). به‌همین دلیل:
 * - gatewayها `normal` کامپایل می‌شوند (مسیریابی خودکار موتور با
 *   guardهای `can:` نمی‌تواند exprهای BPMS را بازتولید کند) — حرکت
 *   mirror همیشه با fire صریح Bridge انجام می‌شود (مسیر واقعی).
 * - تایمرها منتقل نمی‌شوند (تایمر BPMS اکشن-محور است نه گذار).
 *
 * دامنهٔ خالص (آرایه به آرایه).
 *
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class WorkflowDefinitionCompiler
{
    private function __construct()
    {
    }

    /**
     * @return array{id: string, version: int, states: list<array<string, mixed>>, transitions: list<array<string, mixed>>}
     */
    public static function compile(string $name, ProcessModel $model, int $version = 1): array
    {
        $states = [];
        $transitions = [];
        $seenTransitions = [];

        foreach ($model->elements() as $element) {
            $type = match ($element->type) {
                ElementType::StartEvent => 'start',
                ElementType::EndEvent => 'end',
                ElementType::UserTask => 'user_task',
                // 🔒 gateway/serviceTask → normal (بدون رفتار خودکار در mirror)
                default => 'normal',
            };

            $state = ['id' => $element->id, 'type' => $type];
            if ($element->type === ElementType::UserTask && $element->assignee !== null && $element->assignee !== '') {
                $state['assignee'] = $element->assignee;
            }
            if ($element->name !== null && $element->name !== '') {
                $state['meta'] = ['label' => $element->name];
            }
            $states[] = $state;

            foreach ($element->outgoingTargets() as $target) {
                $transitionId = self::transitionId($element->id, $target);
                if (isset($seenTransitions[$transitionId])) {
                    continue;   // یال تکراری (مثلاً دو شرط به یک مقصد)
                }
                $seenTransitions[$transitionId] = true;
                $transitions[] = ['id' => $transitionId, 'from' => $element->id, 'to' => $target];
            }
        }

        return [
            'id' => $name,
            'version' => $version,
            'states' => $states,
            'transitions' => $transitions,
        ];
    }

    /** شناسهٔ گذار mirror برای یال from→to (Bridge با همان fire می‌کند). */
    public static function transitionId(string $from, string $to): string
    {
        return 't_' . $from . '__' . $to;
    }
}
