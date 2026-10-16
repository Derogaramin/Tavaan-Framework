<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Validation\RuleInterface;
use Tavaan\Engine\Validation\Attributes\ValidationAttribute;

/**
 * تَوان | Tavaan — Validation
 * DtoValidator — اعتبارسنجی Attribute-based روی DTO (DOC-051 §6).
 *
 * قواعد از Attributeهای propertyهای constructor خوانده می‌شوند:
 * ```php
 * final class CreateUserDto
 * {
 *     public function __construct(
 *         #[Required, Email, MaxLength(255)]
 *         public readonly string $email,
 *     ) {}
 * }
 * Validator::forDto(CreateUserDto::class, $data)->validate();
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class DtoValidator
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly ValidatorFactory $factory,
        private readonly string $class,
        private readonly array $data,
    ) {
    }

    /**
     * @return array<string, list<RuleInterface>>
     */
    public function rulesFromAttributes(): array
    {
        $reflection = new \ReflectionClass($this->class);
        $rules = [];

        $properties = $reflection->getConstructor()?->getParameters() ?? [];
        foreach ($properties as $parameter) {
            $name = $parameter->getName();
            $target = $reflection->hasProperty($name) ? $reflection->getProperty($name) : null;

            $attributes = [];
            if ($target !== null) {
                $attributes = $target->getAttributes(ValidationAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            }
            if ($attributes === []) {
                $attributes = $parameter->getAttributes(ValidationAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            }

            $ruleObjects = [];
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof RuleInterface) {
                    $ruleObjects[] = $instance;
                }
            }

            // type-hint → قواعد ضمنی (بدون Attribute هم type رعایت شود)
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $type->isBuiltin() && !$parameter->isDefaultValueAvailable()) {
                $builtinRule = match ($type->getName()) {
                    'string' => 'string',
                    'int' => 'integer',
                    'float' => 'numeric',
                    'bool' => 'boolean',
                    'array' => 'array',
                    default => null,
                };
                if ($builtinRule !== null && !$type->allowsNull()) {
                    \array_unshift($ruleObjects, new ParsedRule($builtinRule));
                }
            }

            if ($ruleObjects !== []) {
                $rules[$name] = $ruleObjects;
            }
        }

        return $rules;
    }

    public function make(): Validator
    {
        return $this->factory->make($this->data, $this->rulesFromAttributes());
    }

    /**
     * @return array<string, mixed> دادهٔ معتبرشده
     *
     * @throws \Tavaan\Core\Exceptions\Validation\ValidationException
     */
    public function validate(): array
    {
        return $this->make()->validate();
    }

    public function fails(): bool
    {
        return $this->make()->fails();
    }

    public function errors(): MessageBag
    {
        return $this->make()->errors();
    }
}
