<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

/**
 * تَوان | Tavaan — Container
 * ConstructorSignature — امضای Constructor یک کلاس (قابل کش در فایل).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/container.md
 */
final readonly class ConstructorSignature
{
    /**
     * @param class-string              $class
     * @param list<ParameterSignature>  $parameters
     */
    public function __construct(
        public string $class,
        public bool $hasConstructor,
        public array $parameters,
        public bool $isInstantiable,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'hasConstructor' => $this->hasConstructor,
            'isInstantiable' => $this->isInstantiable,
            'parameters' => \array_map(static fn (ParameterSignature $p): array => $p->toArray(), $this->parameters),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            class: (string) $data['class'],
            hasConstructor: (bool) $data['hasConstructor'],
            parameters: \array_map(
                static fn (array $p): ParameterSignature => ParameterSignature::fromArray($p),
                $data['parameters'] ?? [],
            ),
            isInstantiable: (bool) ($data['isInstantiable'] ?? true),
        );
    }
}
