<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Engine\Config\Exceptions\SchemaValidationException;

/**
 * تَوان | Tavaan — Config
 * SchemaValidator — اعتبارسنجی نوع و قید کلیدهای پیکربندی (DOC-032 §5.5).
 *
 * قواعد نوع: string|int|float|bool|array|list|map|null|mixed|class-string|callable.
 * قیدها: required, nullable, min, max, in, pattern, secret.
 * 🔒 در production فقط required و in بررسی می‌شود (سرعت).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class SchemaValidator
{
    /**
     * @param array<string, mixed> $data   دادهٔ یک namespace
     * @param array<string, mixed> $schema قواعد (کلید dot → قیدها)
     *
     * @throws SchemaValidationException
     */
    public function validate(string $namespace, array $data, array $schema, bool $strict = true): void
    {
        $violations = [];

        foreach ($schema as $key => $rules) {
            $rules = \is_array($rules) ? $rules : [];
            $exists = \Tavaan\Support\Arr::has($data, (string) $key);
            $value = \Tavaan\Support\Arr::get($data, (string) $key);

            if (($rules['required'] ?? false) && (!$exists || $value === null || $value === '')) {
                $violations[] = "{$key}: مقدار الزامی است";
                continue;
            }

            if (!$exists || $value === null) {
                if (($rules['nullable'] ?? false) || !$exists) {
                    continue;
                }
            }

            if (!$strict && !isset($rules['in'])) {
                continue;   // در production فقط required/in
            }

            if (isset($rules['type']) && !$this->checkType($value, (string) $rules['type'])) {
                $violations[] = "{$key}: نوع نامعتبر (انتظار {$rules['type']})";
                continue;
            }

            if (isset($rules['in']) && !\in_array($value, (array) $rules['in'], true)) {
                $violations[] = "{$key}: مقدار خارج از فهرست مجاز";
            }

            if (isset($rules['pattern']) && \is_string($value) && \preg_match((string) $rules['pattern'], $value) !== 1) {
                $violations[] = "{$key}: الگوی نامعتبر";
            }

            if (isset($rules['min']) && (\is_int($value) || \is_float($value)) && $value < $rules['min']) {
                $violations[] = "{$key}: کمتر از حداقل {$rules['min']}";
            }

            if (isset($rules['max'])) {
                if ((\is_int($value) || \is_float($value)) && $value > $rules['max']) {
                    $violations[] = "{$key}: بیشتر از حداکثر {$rules['max']}";
                }
                if (\is_string($value) && \mb_strlen($value) > (int) $rules['max']) {
                    $violations[] = "{$key}: طول بیشتر از {$rules['max']}";
                }
            }
        }

        if ($violations !== []) {
            throw SchemaValidationException::for($namespace, $violations);
        }
    }

    private function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string'       => \is_string($value),
            'int'          => \is_int($value),
            'float'        => \is_float($value) || \is_int($value),
            'bool'         => \is_bool($value),
            'array'        => \is_array($value),
            'list'         => \is_array($value) && \array_is_list($value),
            'map'          => \is_array($value) && !\array_is_list($value),
            'null'         => $value === null,
            'mixed'        => true,
            'class-string' => \is_string($value) && (\class_exists($value) || \interface_exists($value)),
            'callable'     => \is_callable($value),
            default        => true,
        };
    }
}
