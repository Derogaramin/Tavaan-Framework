<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * ModelValidator — اعتبارسنجی عمیق تعریف فرایند (AC-BPMS-11).
 */

namespace Modules\Bpms\Domain\Services;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\ValueObjects\Element;
use Modules\Bpms\Domain\ValueObjects\FormSchema;
use Modules\Bpms\Domain\ValueObjects\ProcessModel;

/**
 * ModelValidator — تعریف JSON را پیش از publish/start راستی‌آزمایی می‌کند
 * تا «خروجی Designer معتبر و قابل اجرا» تضمین شود (AC-BPMS-11):
 * ساختار، شناسه‌ها، یال‌ها، assignee/action/شرط‌ها، تایمرها و فرم‌ها.
 *
 * دامنهٔ خالص — فهرست مشکلات (خالی = معتبر).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class ModelValidator
{
    /** الگوی شناسهٔ عنصر (سازگار با موتور workflow برای mirror). */
    private const ID_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    /** سقف عناصر/تایمرها (🔒 DoS — DOC-071 §8). */
    private const MAX_ELEMENTS = 200;

    private const MAX_TIMERS = 50;

    private const MAX_FORMS = 50;

    private readonly ExpressionEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new ExpressionEvaluator();
    }

    /**
     * اعتبارسنجی کامل — فهرست مشکلات (خالی = OK).
     *
     * @param array<string, mixed>          $definitionJson بخش definition
     * @param array<string, array<string, mixed>> $formsJson       کلید → form schema خام
     *
     * @return list<string>
     */
    public function validate(array $definitionJson, array $formsJson = []): array
    {
        $problems = [];

        try {
            $model = ProcessModel::fromArray($definitionJson);
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }

        $elements = $model->elements();
        if (\count($elements) > self::MAX_ELEMENTS) {
            $problems[] = 'تعداد عناصر بیش از سقف مجاز است (' . self::MAX_ELEMENTS . ').';
        }
        if (\count($model->timers) > self::MAX_TIMERS) {
            $problems[] = 'تعداد تایمرها بیش از سقف مجاز است (' . self::MAX_TIMERS . ').';
        }

        $startCount = 0;
        $endCount = 0;

        foreach ($elements as $element) {
            if (\preg_match(self::ID_PATTERN, $element->id) !== 1) {
                $problems[] = 'شناسهٔ نامعتبر: ' . $element->id;
            }

            match ($element->type) {
                ElementType::StartEvent => $startCount++,
                ElementType::EndEvent => $endCount++,
                default => null,
            };

            // next/conditions/fork → عناصر موجود
            foreach ($element->outgoingTargets() as $target) {
                if (!isset($elements[$target])) {
                    $problems[] = 'مقصد ناشناخته «' . $target . '» در عنصر «' . $element->id . '».';
                }
            }

            // قواعد per نوع
            $problems = [...$problems, ...$this->validateByType($element, $model, $formsJson)];
        }

        if ($startCount !== 1) {
            $problems[] = 'فرایند باید دقیقاً یک startEvent داشته باشد (یافت‌شده: ' . $startCount . ').';
        }
        if ($endCount === 0) {
            $problems[] = 'فرایند هیچ endEvent ندارد.';
        }

        // startEvent باید دست‌کم یک خروجی داشته باشد
        $start = $model->element($model->startEvent);
        if ($start !== null && $start->outgoingTargets() === []) {
            $problems[] = 'startEvent هیچ خروجی ندارد.';
        }

        // عناصر یتیم (بدون یال ورودی و خروجی — غیر start/end)
        foreach ($elements as $element) {
            if ($element->type === ElementType::StartEvent || $element->type === ElementType::EndEvent) {
                continue;
            }
            if ($model->incomingCount($element->id) === 0 && $element->outgoingTargets() === []) {
                $problems[] = 'عنصر «' . $element->id . '» ایزوله است (بدون یال).';
            }
            if ($model->incomingCount($element->id) === 0) {
                $problems[] = 'عنصر «' . $element->id . '» هیچ یال ورودی ندارد (غیرقابل‌رسیدن).';
            }
        }

        // تایمرها
        foreach ($model->timers as $timer) {
            if (!isset($elements[$timer->on])) {
                $problems[] = 'تایمر به عنصر ناشناخته «' . $timer->on . '» اشاره دارد.';
            }
            if ($timer->action === '') {
                $problems[] = 'تایمر روی «' . $timer->on . '» اکشن ندارد.';
            }
            if (!DurationParser::isValid($timer->after)) {
                $problems[] = 'مدت تایمر نامعتبر است: «' . $timer->after . '».';
            }
        }

        // فرم‌ها
        if (\count($formsJson) > self::MAX_FORMS) {
            $problems[] = 'تعداد فرم‌ها بیش از سقف مجاز است (' . self::MAX_FORMS . ').';
        }
        foreach ($formsJson as $formId => $rawForm) {
            try {
                $schema = FormSchema::fromArray((array) $rawForm);
                if ($schema->fields === []) {
                    $problems[] = 'فرم «' . $formId . '» هیچ فیلدی ندارد.';
                }
            } catch (\InvalidArgumentException $e) {
                $problems[] = 'فرم «' . $formId . '» نامعتبر: ' . $e->getMessage();
            }
        }

        return \array_values(\array_unique($problems));
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return list<string>
     */
    public function validateDefinitionArray(array $element): array
    {
        $definition = (array) ($element['definition'] ?? []);
        $forms = (array) ($element['form_schema'] ?? []);

        $problems = [];
        $key = \trim((string) ($element['key'] ?? ''));
        if ($key === '' || \preg_match('/^[a-z0-9][a-z0-9_\-]*$/', $key) !== 1) {
            $problems[] = 'کلید فرایند نامعتبر است (فقط حروف کوچک/رقم/_/-).';
        }
        if (\trim((string) ($element['name'] ?? '')) === '') {
            $problems[] = 'نام فرایند خالی است.';
        }

        return [...$problems, ...$this->validate($definition, $forms)];
    }

    /**
     * @param array<string, array<string, mixed>> $formsJson
     *
     * @return list<string>
     */
    private function validateByType(Element $element, ProcessModel $model, array $formsJson): array
    {
        $problems = [];
        $id = $element->id;

        switch ($element->type) {
            case ElementType::UserTask:
                if ($element->outgoingTargets() === []) {
                    $problems[] = 'userTask «' . $id . '» هیچ خروجی (next) ندارد.';
                }
                if ($element->due !== null && !DurationParser::isValid($element->due)) {
                    $problems[] = 'مدت due در «' . $id . '» نامعتبر است: «' . $element->due . '».';
                }
                if ($element->form !== null && $element->form !== '' && !isset($formsJson[$element->form])) {
                    $problems[] = 'فرم «' . $element->form . '» برای «' . $id . '» در form_schema تعریف نشده است.';
                }
                break;

            case ElementType::ServiceTask:
                if ($element->action === null || $element->action === '') {
                    $problems[] = 'serviceTask «' . $id . '» اکشن ثبت‌شده ندارد.';
                }
                if ($element->outgoingTargets() === []) {
                    $problems[] = 'serviceTask «' . $id . '» هیچ خروجی ندارد.';
                }
                break;

            case ElementType::ExclusiveGateway:
                $problems = [...$problems, ...$this->validateExclusiveGateway($element)];
                break;

            case ElementType::ParallelGateway:
                $incoming = $model->incomingCount($id);
                $outgoing = \count($element->outgoingTargets());
                if ($incoming > 1 && $outgoing > 1) {
                    $problems[] = 'parallelGateway «' . $id . '» همزمان fork و join است (تفکیک شود).';
                }
                if ($incoming <= 1 && $outgoing <= 1) {
                    $problems[] = 'parallelGateway «' . $id . '» بی‌اثر است (نه fork با چند خروجی، نه join با چند ورودی).';
                }
                break;

            case ElementType::StartEvent:
                if ($element->outgoingTargets() === []) {
                    $problems[] = 'startEvent «' . $id . '» خروجی ندارد.';
                }
                break;

            case ElementType::EndEvent:
                if ($element->outgoingTargets() !== []) {
                    $problems[] = 'endEvent «' . $id . '» نباید خروجی داشته باشد.';
                }
                break;
        }

        return $problems;
    }

    /** @return list<string> */
    private function validateExclusiveGateway(Element $element): array
    {
        $problems = [];
        $id = $element->id;

        if ($element->conditions === []) {
            $problems[] = 'exclusiveGateway «' . $id . '» هیچ شرطی ندارد.';

            return $problems;
        }

        $defaults = 0;
        foreach ($element->conditions as $condition) {
            if ($condition->default) {
                $defaults++;
            } elseif ($condition->expr === null) {
                $problems[] = 'شرط غیرdefault در «' . $id . '» عبارت expr ندارد.';
            } elseif (!$this->evaluator->parses($condition->expr)) {
                // 🔒 AC-BPMS-12 — عبارت باید با evaluator امن پارس شود (بدون eval/تابع/تزریق)
                $problems[] = 'عبارت شرط در «' . $id . '» نامعتبر یا ناامن است: «' . $condition->expr . '».';
            }
            if ($condition->next === '') {
                $problems[] = 'شرطی در «' . $id . '» مقصد next ندارد.';
            }
        }
        if ($defaults === 0) {
            $problems[] = 'exclusiveGateway «' . $id . '» مسیر default ندارد (بن‌بست احتمالی در اجرا).';
        }
        if ($defaults > 1) {
            $problems[] = 'exclusiveGateway «' . $id . '» بیش از یک مسیر default دارد.';
        }

        return $problems;
    }
}
