<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Form;

/**
 * تَوان | Tavaan — Admin
 * Field — تعریف یک فیلد فرم با Fluent API (DOC-078 §2/§6).
 *
 * قواعد اعتبارسنجی از type + modifierها ساخته می‌شوند (`rules()`) →
 * Validation خودکار فرم (AC-ADM §3).
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Field
{
    private string $label = '';

    /** @var list<string> قواعد صریح (علاوه بر قواعد type) */
    private array $extraRules = [];

    /** @var array<string, mixed> */
    private array $attrs = [];

    /** @var array<array-key, mixed> گزینه‌های select/radio */
    private array $options = [];

    private mixed $default = null;

    private bool $isRequired = false;

    private bool $isNullable = false;

    private ?int $maxLength = null;

    private ?int $minLength = null;

    private int|float|null $minValue = null;

    private int|float|null $maxValue = null;

    private ?string $slugFrom = null;

    private ?string $sanitizerProfile = null;

    private ?string $relationModel = null;

    private string $relationDisplay = 'id';

    private ?Repeater $repeater = null;

    private ?string $disk = null;

    private bool $isMultiple = false;

    private string $jalaliFormat = 'Y/m/d';

    private string $currency = 'IRR';

    public function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
        $this->label = self::humanize($name);
    }

    // ─── Fluent modifiers ─────────────────────────────────────────────────

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->isRequired = $required;

        return $this;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->isNullable = $nullable;

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    public function minLength(int $length): self
    {
        $this->minLength = $length;

        return $this;
    }

    public function min(int|float $value): self
    {
        $this->minValue = $value;

        return $this;
    }

    public function max(int|float $value): self
    {
        $this->maxValue = $value;

        return $this;
    }

    /**
     * گزینه‌های select/radio — آرایه یا class-string از enum (backed).
     *
     * @param array<array-key, mixed>|class-string<\BackedEnum> $options
     */
    public function options(array|string $options): self
    {
        if (\is_string($options)) {
            $resolved = [];
            if (\enum_exists($options)) {
                foreach ($options::cases() as $case) {
                    /** @var \BackedEnum $case */
                    $resolved[(string) $case->value] = $case->name;
                }
            }
            $this->options = $resolved;
        } else {
            $this->options = $options;
        }

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->attrs['placeholder'] = $placeholder;

        return $this;
    }

    public function help(string $help): self
    {
        $this->attrs['help'] = $help;

        return $this;
    }

    public function rows(int $rows): self
    {
        $this->attrs['rows'] = $rows;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /** slug از روی فیلد دیگر ساخته شود (`slug('slug')->from('title')`). */
    public function from(string $fieldName): self
    {
        $this->slugFrom = $fieldName;

        return $this;
    }

    public function sanitizer(string $profile): self
    {
        $this->sanitizerProfile = $profile;

        return $this;
    }

    /** @param class-string $model */
    public function model(string $model): self
    {
        $this->relationModel = $model;

        return $this;
    }

    public function display(string $column): self
    {
        $this->relationDisplay = $column;

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->isMultiple = $multiple;

        return $this;
    }

    public function format(string $format): self
    {
        $this->jalaliFormat = $format;

        return $this;
    }

    /** قواعد صریح اضافی. @param list<string> $rules */
    public function rules(array $rules): self
    {
        $this->extraRules = [...$this->extraRules, ...$rules];

        return $this;
    }

    public function attr(string $key, mixed $value): self
    {
        $this->attrs[$key] = $value;

        return $this;
    }

    /** ثبت زیرفیلدهای repeater. */
    public function fields(\Closure $configure): self
    {
        $repeater = new Repeater($this->name);
        $configure($repeater);
        $this->repeater = $repeater;

        return $this;
    }

    // ─── getters ──────────────────────────────────────────────────────────

    public function labelText(): string
    {
        return $this->label;
    }

    /** @return array<array-key, mixed> */
    public function optionsList(): array
    {
        return $this->options;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /** @return array<string, mixed> */
    public function attrs(): array
    {
        return $this->attrs;
    }

    public function slugFrom(): ?string
    {
        return $this->slugFrom;
    }

    public function sanitizerProfile(): ?string
    {
        return $this->sanitizerProfile;
    }

    public function relationModel(): ?string
    {
        return $this->relationModel;
    }

    public function relationDisplay(): string
    {
        return $this->relationDisplay;
    }

    public function repeater(): ?Repeater
    {
        return $this->repeater;
    }

    public function diskName(): ?string
    {
        return $this->disk;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function jalaliFormatValue(): string
    {
        return $this->jalaliFormat;
    }

    public function currencyCode(): string
    {
        return $this->currency;
    }

    /**
     * قواعد اعتبارسنجی محاسبه‌شده (type + modifierها + صریح).
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $rules = [];

        if ($this->isRequired && !\in_array($this->type, ['static', 'hidden'], true)) {
            $rules[] = 'required';
        } else {
            // فیلد اختیاری → nullable (قواعد بعدی روی null اجرا نمی‌شوند)
            $rules[] = 'nullable';
        }

        $typeRules = match ($this->type) {
            'text', 'slug', 'code', 'color', 'password', 'richtext' => ['string'],
            'textarea' => ['string'],
            'number' => ['numeric'],
            'money' => ['numeric'],
            'email' => ['email'],
            'select', 'radio' => $this->options === [] ? ['string'] : ['in:' . \implode(',', \array_map('strval', \array_keys($this->options)))],
            'multiselect', 'tags' => ['array'],
            'checkbox', 'switch' => ['boolean'],
            'date' => ['date'],
            'datetime' => ['date'],
            'time' => ['regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'jalali_date' => ['regex:/^\d{4}\/\d{1,2}\/\d{1,2}( [0-9:]{5,8})?$/'],
            'json' => ['array'],
            'relation' => ['present'],
            'file', 'media' => $this->isMultiple ? ['array'] : ['string'],
            'repeater' => ['array'],
            'hidden' => ['string'],
            default => [],   // static
        };

        foreach ($typeRules as $rule) {
            $rules[] = $rule;
        }

        if ($this->maxLength !== null && \in_array($this->type, ['text', 'textarea', 'slug', 'code', 'richtext', 'password', 'email'], true)) {
            $rules[] = 'max:' . $this->maxLength;
        }
        if ($this->minLength !== null) {
            $rules[] = 'min:' . $this->minLength;
        }
        if ($this->minValue !== null && \in_array($this->type, ['number', 'money'], true)) {
            $rules[] = 'min:' . $this->minValue;
        }
        if ($this->maxValue !== null && \in_array($this->type, ['number', 'money'], true)) {
            $rules[] = 'max:' . $this->maxValue;
        }

        return [...$rules, ...$this->extraRules];
    }

    private static function humanize(string $name): string
    {
        return \ucfirst(\str_replace(['_', '-'], ' ', $name));
    }
}
