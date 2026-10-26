<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * FormSchema — فرم پویا (DOC-098 §4) + compile به قواعد validation.
 */

namespace Modules\Bpms\Domain\ValueObjects;

use Modules\Bpms\Domain\Enums\FieldType;

/**
 * FormSchema — تغییرناپذیر: id + fields. `toValidationRules()` قواعد موتور
 * validation را از schema تولید می‌کند (§4: «validation از form_schema
 * تولید می‌شود») — دامنهٔ خالص، بدون وابستگی.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class FormSchema
{
    /**
     * @param list<FormField> $fields
     */
    public function __construct(
        public string $id,
        public array $fields,
        public ?string $title = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException ساختار نامعتبر
     */
    public static function fromArray(array $data): self
    {
        $id = \trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            throw new \InvalidArgumentException('form schema id is empty');
        }

        $fields = [];
        $seen = [];
        foreach ((array) ($data['fields'] ?? []) as $raw) {
            if (!\is_array($raw)) {
                continue;
            }
            $field = FormField::fromArray($raw);
            if (isset($seen[$field->name])) {
                throw new \InvalidArgumentException('duplicate field name: ' . $field->name);
            }
            $seen[$field->name] = true;
            $fields[] = $field;
        }

        return new self(
            id: $id,
            fields: $fields,
            title: isset($data['title']) && \is_scalar($data['title']) ? (string) $data['title'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $field) {
            $fields[] = $field->toArray();
        }

        return \array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'fields' => $fields,
        ], static fn (mixed $v): bool => $v !== null);
    }

    public function field(string $name): ?FormField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * تولید قواعد validation موتور (DOC-098 §4).
     *
     * @return array<string, list<string>> fieldName → قواعد
     */
    public function toValidationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $fieldRules = [];
            if ($field->required) {
                $fieldRules[] = 'required';
            } elseif ($field->type !== FieldType::Hidden) {
                $fieldRules[] = 'nullable';
            }

            match ($field->type) {
                FieldType::Number => self::numericRules($field, $fieldRules),
                FieldType::Text, FieldType::Textarea => self::stringRules($field, $fieldRules),
                FieldType::Select => self::selectRules($field, $fieldRules),
                FieldType::Checkbox => $fieldRules[] = 'boolean',
                FieldType::JalaliDate => $fieldRules[] = 'jalali_date',
                FieldType::Date => $fieldRules[] = 'date',
                FieldType::Email => $fieldRules[] = 'email',
                FieldType::Mobile => $fieldRules[] = 'iran_mobile',
                FieldType::Hidden => null,
            };

            if ($fieldRules !== []) {
                $rules[$field->name] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * نرمال‌سازی دادهٔ خام فرم بر اساس نوع فیلد (رشته → عدد/bool).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        $normalized = [];
        foreach ($this->fields as $field) {
            if (!\array_key_exists($field->name, $data)) {
                if ($field->type === FieldType::Checkbox) {
                    $normalized[$field->name] = false;   // checkbox ارسال‌نشده = خاموش
                }
                continue;
            }
            $value = $data[$field->name];
            $normalized[$field->name] = match ($field->type) {
                FieldType::Number => $value === null || $value === '' ? null : (is_numeric($value) ? $value + 0 : $value),
                FieldType::Checkbox => \in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true),
                FieldType::Text, FieldType::Textarea, FieldType::Select, FieldType::Email, FieldType::Mobile, FieldType::JalaliDate, FieldType::Date => \is_scalar($value) ? \trim((string) $value) : $value,
                FieldType::Hidden => $value,
            };
        }

        // کلیدهای ناشناخته (خارج از schema) هرگز پذیرفته نمی‌شوند 🔒
        return $normalized;
    }

    /** @param list<string> $rules */
    private static function numericRules(FormField $field, array &$rules): void
    {
        $rules[] = 'numeric';
        if ($field->min !== null) {
            $rules[] = 'min:' . $field->min;
        }
        if ($field->max !== null) {
            $rules[] = 'max:' . $field->max;
        }
    }

    /** @param list<string> $rules */
    private static function stringRules(FormField $field, array &$rules): void
    {
        $rules[] = 'string';
        if ($field->max !== null) {
            $rules[] = 'max:' . $field->max;
        } elseif ($field->type === FieldType::Text) {
            $rules[] = 'max:500';   // سقف محافظتی پیش‌فرض
        }
    }

    /** @param list<string> $rules */
    private static function selectRules(FormField $field, array &$rules): void
    {
        if ($field->options !== []) {
            // escape separator در گزینه‌ها (`,` و `\` در in: مجاز نیستند)
            $safe = [];
            foreach ($field->options as $option) {
                if (!\str_contains($option, ',') && !\str_contains($option, '\\')) {
                    $safe[] = $option;
                }
            }
            if ($safe !== []) {
                $rules[] = 'in:' . \implode(',', $safe);
            }
        }
    }
}
