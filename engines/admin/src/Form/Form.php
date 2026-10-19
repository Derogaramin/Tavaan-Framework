<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Form;

/**
 * تَوان | Tavaan — Admin
 * Form — سازندهٔ فیلدهای فرم (DOC-078 §2/§6).
 *
 * همهٔ ۲۷ نوع فیلد §6 از طریق `__call` با whitelist صریح پشتیبانی می‌شوند:
 * ```php
 * $r->form(function (Form $f): void {
 *     $f->text('title')->required()->maxLength(255);
 *     $f->jalaliDate('published_at')->format('Y/m/d');
 * });
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Form
{
    /** انواع مجاز فیلد (DOC-078 §6) — کلید = نام متد، مقدار = type داخلی */
    public const TYPES = [
        'text' => 'text',
        'textarea' => 'textarea',
        'number' => 'number',
        'money' => 'money',
        'email' => 'email',
        'password' => 'password',
        'select' => 'select',
        'multiselect' => 'multiselect',
        'checkbox' => 'checkbox',
        'radio' => 'radio',
        'switch' => 'switch',
        'date' => 'date',
        'jalaliDate' => 'jalali_date',
        'jalali_date' => 'jalali_date',
        'time' => 'time',
        'datetime' => 'datetime',
        'file' => 'file',
        'media' => 'media',
        'richtext' => 'richtext',
        'code' => 'code',
        'json' => 'json',
        'relation' => 'relation',
        'repeater' => 'repeater',
        'hidden' => 'hidden',
        'static' => 'static',
        'color' => 'color',
        'slug' => 'slug',
        'tags' => 'tags',
    ];

    /** @var list<Field> */
    private array $fields = [];

    /**
     * `$f->text('title')` → Field.
     *
     * @param list<mixed> $arguments
     *
     * @throws \BadMethodCallException نوع ناشناخته
     */
    public function __call(string $method, array $arguments): Field
    {
        if (!isset(self::TYPES[$method])) {
            throw new \BadMethodCallException(\sprintf(
                'Form: field type [%s] is not supported (DOC-078 §6).',
                $method,
            ));
        }

        $name = (string) ($arguments[0] ?? '');
        if ($name === '' || \preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Form: field name [%s] must be snake_case.',
                $name,
            ));
        }

        $field = new Field($name, self::TYPES[$method]);

        // repeater: آرگومان دوم Closure زیرفیلدها (§2)
        if ($field->type === 'repeater' && isset($arguments[1]) && $arguments[1] instanceof \Closure) {
            $field->fields($arguments[1]);
        }

        $this->fields[] = $field;

        return $field;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * whitelist نام فیلدها (محافظ Mass Assignment — DOC-078 §7).
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->fields as $field) {
            if ($field->type !== 'static') {
                $names[] = $field->name;
            }
        }

        return $names;
    }

    /**
     * قواعد اعتبارسنجی کل فرم (Validation خودکار — §3).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            if ($field->type === 'static') {
                continue;
            }
            $fieldRules = $field->validationRules();
            if ($fieldRules !== []) {
                $rules[$field->name] = $fieldRules;
            }
        }

        return $rules;
    }
}
