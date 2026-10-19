<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin\Form;

/**
 * تَوان | Tavaan — Admin
 * Repeater — زیرفرم تکرارشونده (DOC-078 §2: `$f->repeater('attributes', fn(Repeater $rep) => …)`).
 *
 * دادهٔ ارسالی: `name[index][subfield]` → list<array<string,mixed>>.
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Repeater
{
    /** @var list<Field> */
    private array $fields = [];

    public function __construct(public readonly string $name)
    {
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): Field
    {
        if (!isset(Form::TYPES[$method])) {
            throw new \BadMethodCallException(\sprintf('Repeater: field type [%s] is not supported.', $method));
        }
        if ($method === 'repeater') {
            throw new \BadMethodCallException('Repeater: nested repeater is not supported.');
        }

        $name = (string) ($arguments[0] ?? '');
        if ($name === '' || \preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Repeater: field name [%s] must be snake_case.', $name));
        }

        $field = new Field($name, Form::TYPES[$method]);
        $this->fields[] = $field;

        return $field;
    }

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }
}
