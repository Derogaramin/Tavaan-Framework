<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

/**
 * تَوان | Tavaan — Container
 * ParameterSignature — توصیف سریالایز‌پذیر یک پارامتر Constructor (بدون ReflectionParameter).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/container.md
 */
final readonly class ParameterSignature
{
    /**
     * @param list<array{kind: string, names: list<string>}> $unionParts بخش‌های Union/DNF
     */
    public function __construct(
        public string $name,
        public ?string $typeString,
        public bool $isClass,
        public bool $isUnion,
        public bool $isIntersection,
        public bool $isBuiltin,
        public string $builtinType,
        public bool $nullable,
        public bool $hasDefault,
        public mixed $default,
        public bool $isVariadic,
        public bool $isPromoted,
        public bool $byReference,
        public array $unionParts = [],
        public bool $isIntersectionTyped = false,
        /** @var list<string> اعضای Intersection */
        public array $intersectionNames = [],
    ) {
    }

    /** ساخت از ReflectionParameter. */
    public static function fromReflection(\ReflectionParameter $p): self
    {
        $type = $p->getType();

        $isClass = false;
        $typeString = null;
        $isUnion = false;
        $isIntersection = false;
        $isBuiltin = false;
        $builtinType = '';
        $unionParts = [];
        $intersectionNames = [];

        if ($type instanceof \ReflectionNamedType) {
            if ($type->isBuiltin()) {
                $isBuiltin = true;
                $builtinType = $type->getName();
            } else {
                $isClass = true;
                $typeString = $type->getName();
            }
        } elseif ($type instanceof \ReflectionUnionType) {
            $isUnion = true;
            foreach ($type->getTypes() as $part) {
                if ($part instanceof \ReflectionIntersectionType) {
                    $names = [];
                    foreach ($part->getTypes() as $ip) {
                        // getTypes() در intersection همیشه ReflectionNamedType است
                        if ($ip instanceof \ReflectionNamedType) {
                            $names[] = $ip->getName();
                        }
                    }
                    $unionParts[] = ['kind' => 'intersection', 'names' => $names];
                } elseif ($part instanceof \ReflectionNamedType) {
                    if ($part->isBuiltin()) {
                        $unionParts[] = ['kind' => 'builtin', 'names' => [$part->getName()]];
                    } else {
                        $name = $part->getName();
                        $kind = \interface_exists($name) ? 'interface' : 'class';
                        $unionParts[] = ['kind' => $kind, 'names' => [$name]];
                    }
                }
            }
        } elseif ($type instanceof \ReflectionIntersectionType) {
            $isIntersection = true;
            foreach ($type->getTypes() as $ip) {
                if ($ip instanceof \ReflectionNamedType) {
                    $intersectionNames[] = $ip->getName();
                }
            }
        }

        $hasDefault = $p->isDefaultValueAvailable();
        $default = null;
        if ($hasDefault) {
            $default = $p->isDefaultValueConstant()
                ? ['__const' => (string) $p->getDefaultValueConstantName()]
                : $p->getDefaultValue();
        }

        return new self(
            name: $p->getName(),
            typeString: $typeString,
            isClass: $isClass,
            isUnion: $isUnion,
            isIntersection: $isIntersection,
            isBuiltin: $isBuiltin,
            builtinType: $builtinType,
            nullable: $type === null ? true : $p->allowsNull(),
            hasDefault: $hasDefault,
            default: $default,
            isVariadic: $p->isVariadic(),
            isPromoted: $p->isPromoted(),
            byReference: $p->isPassedByReference(),
            unionParts: $unionParts,
            isIntersectionTyped: $isIntersection,
            intersectionNames: $intersectionNames,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'typeString' => $this->typeString,
            'isClass' => $this->isClass,
            'isUnion' => $this->isUnion,
            'isIntersection' => $this->isIntersection,
            'isBuiltin' => $this->isBuiltin,
            'builtinType' => $this->builtinType,
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => $this->default,
            'isVariadic' => $this->isVariadic,
            'isPromoted' => $this->isPromoted,
            'byReference' => $this->byReference,
            'unionParts' => $this->unionParts,
            'isIntersectionTyped' => $this->isIntersectionTyped,
            'intersectionNames' => $this->intersectionNames,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            typeString: $data['typeString'] ?? null,
            isClass: (bool) $data['isClass'],
            isUnion: (bool) $data['isUnion'],
            isIntersection: (bool) $data['isIntersection'],
            isBuiltin: (bool) $data['isBuiltin'],
            builtinType: (string) ($data['builtinType'] ?? ''),
            nullable: (bool) $data['nullable'],
            hasDefault: (bool) $data['hasDefault'],
            default: $data['default'] ?? null,
            isVariadic: (bool) $data['isVariadic'],
            isPromoted: (bool) ($data['isPromoted'] ?? false),
            byReference: (bool) ($data['byReference'] ?? false),
            unionParts: $data['unionParts'] ?? [],
            isIntersectionTyped: (bool) ($data['isIntersectionTyped'] ?? false),
            intersectionNames: $data['intersectionNames'] ?? [],
        );
    }

    /** مقدار default واقعی (با resolve ثابت‌های نمادین). */
    public function defaultValue(): mixed
    {
        if (\is_array($this->default) && \array_key_exists('__const', $this->default)) {
            $const = $this->default['__const'];
            if (\defined($const)) {
                return \constant($const);
            }
            if (\str_contains($const, '::')) {
                [$class, $name] = \explode('::', $const, 2);
                if (\defined($class . '::' . $name)) {
                    return \constant($class . '::' . $name);
                }
            }

            return null;
        }

        return $this->default;
    }
}
