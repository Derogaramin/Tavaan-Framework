<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * FormField — یک فیلد فرم‌ساز پویا (DOC-098 §4).
 */

namespace Modules\Bpms\Domain\ValueObjects;

use Modules\Bpms\Domain\Enums\FieldType;

/**
 * FormField — تغییرناپذیر: name/type/label + محدودیت‌ها (required/min/max/
 * options/placeholder/rows). نگاشت type → قواعد validation در FormSchema.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class FormField
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $label,
        public bool $required = false,
        public ?int $min = null,
        public ?int $max = null,
        public array $options = [],
        public ?string $placeholder = null,
        public ?string $help = null,
        public int $rows = 3,
        public mixed $default = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException نام خالی / نوع ناشناخته
     */
    public static function fromArray(array $data): self
    {
        $name = \trim((string) ($data['name'] ?? ''));
        if ($name === '' || \preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('field name invalid: ' . $name);
        }
        $typeRaw = (string) ($data['type'] ?? 'text');
        $type = FieldType::tryFrom($typeRaw);
        if ($type === null) {
            throw new \InvalidArgumentException('unknown field type: ' . $typeRaw);
        }

        $options = [];
        foreach ((array) ($data['options'] ?? []) as $option) {
            if (\is_scalar($option)) {
                $options[] = (string) $option;
            }
        }

        $toNullableInt = static fn (mixed $v): ?int => $v === null || $v === '' || !\is_numeric($v) ? null : (int) $v;

        return new self(
            name: $name,
            type: $type,
            label: (string) ($data['label'] ?? $name),
            required: (bool) ($data['required'] ?? false),
            min: $toNullableInt($data['min'] ?? null),
            max: $toNullableInt($data['max'] ?? null),
            options: $options,
            placeholder: isset($data['placeholder']) && \is_scalar($data['placeholder']) ? (string) $data['placeholder'] : null,
            help: isset($data['help']) && \is_scalar($data['help']) ? (string) $data['help'] : null,
            rows: \max(1, (int) ($data['rows'] ?? 3)),
            default: \is_scalar($data['default'] ?? null) ? $data['default'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return \array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'label' => $this->label,
            'required' => $this->required ? true : null,
            'min' => $this->min,
            'max' => $this->max,
            'options' => $this->options === [] ? null : $this->options,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'rows' => $this->rows === 3 ? null : $this->rows,
            'default' => $this->default,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
