<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Casts;

use Tavaan\Core\Contracts\ORM\CastInterface;
use Tavaan\Engine\Compat\Decimal;
use Tavaan\Engine\Database\ORM\Model;
use Tavaan\Engine\Database\ORM\Support\Money;

/**
 * تَوان | Tavaan — ORM
 * CastHandler — تبدیل صفات خام DB ↔ مقادیر دامنه (DOC-050 §5).
 *
 * Castهای داخلی: int/float/string/bool/array/object/json/collection،
 * datetime/date/timestamp/immutable_datetime، decimal:N، money:CUR،
 * encrypted، hashed، ulid/uuid، و Enum class-string + CastInterface سفارشی.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class CastHandler
{
    /**
     * @param array<string, mixed> $attributes صفات خام
     */
    public static function get(Model $model, string $key, mixed $value, array $attributes, string $cast): mixed
    {
        if ($value === null) {
            return null;
        }

        // Cast سفارشی (CastInterface)
        if (\class_exists($cast) && \is_subclass_of($cast, CastInterface::class)) {
            /** @var CastInterface $instance */
            $instance = new $cast();

            return $instance->get($model, $key, $value, $attributes);
        }

        // Enum backed
        if (\enum_exists($cast) && \is_subclass_of($cast, \BackedEnum::class)) {
            return $cast::from(self::scalar($value));
        }

        [$type, $param] = \array_pad(\explode(':', $cast, 2), 2, null);

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => self::decodeArray($value),
            'collection' => self::decodeArray($value),
            'object' => \is_string($value) ? \json_decode($value, flags: JSON_THROW_ON_ERROR) : $value,
            'datetime', 'immutable_datetime' => self::toDateTime($value),
            'date' => self::toDateTime($value)?->setTime(0, 0),
            'timestamp' => \is_numeric($value) ? (int) $value : (self::toDateTime($value)?->getTimestamp() ?? 0),
            'decimal' => Decimal::of($value, $param === null ? null : (int) $param),
            'money' => Money::of((int) $value, $param ?? 'IRR'),
            'encrypted' => self::decrypt($value),
            'hashed' => (string) $value,   // یک‌طرفه — فقط خواندن
            'ulid', 'uuid' => (string) $value,
            default => $value,
        };
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function set(Model $model, string $key, mixed $value, array $attributes, string $cast): mixed
    {
        // Cast سفارشی
        if (\class_exists($cast) && \is_subclass_of($cast, CastInterface::class)) {
            /** @var CastInterface $instance */
            $instance = new $cast();

            return $instance->set($model, $key, $value, $attributes);
        }

        // Enum backed
        if (\enum_exists($cast) && \is_subclass_of($cast, \BackedEnum::class)) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }

            return $value === null ? null : $cast::from(self::scalar($value))->value;
        }

        if ($value === null) {
            return null;
        }

        [$type, $param] = \array_pad(\explode(':', $cast, 2), 2, null);

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => $value ? 1 : 0,
            'array', 'json', 'collection' => \is_string($value) ? $value : (string) \json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'object' => \is_string($value) ? $value : (string) \json_encode($value, JSON_THROW_ON_ERROR),
            'datetime', 'immutable_datetime', 'date' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s.u')
                : (string) $value,
            'timestamp' => $value instanceof \DateTimeInterface ? $value->getTimestamp() : (int) $value,
            'decimal' => $value instanceof Decimal ? $value->toString() : (string) $value,
            'money' => match (true) {
                $value instanceof Money => $value->amount,
                \is_int($value) => $value,
                \is_string($value) && \preg_match('/^\d+$/', $value) === 1 => (int) $value,
                default => throw new \InvalidArgumentException('money cast expects int minor-unit or Money (ADR-0013).'),
            },
            'encrypted' => self::encrypt($value),
            'hashed' => self::hash($value),
            'ulid' => $value instanceof \Tavaan\Support\Ulid ? $value->toString() : (string) $value,
            default => $value,
        };
    }

    public static function isJsonCast(string $cast): bool
    {
        return \in_array($cast, ['array', 'json', 'object', 'collection'], true);
    }

    /** @return array<array-key, mixed> */
    private static function decodeArray(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        if (!\is_string($value) || $value === '') {
            return [];
        }

        $decoded = \json_decode($value, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private static function toDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (\is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
        }
        if (\is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private static function scalar(mixed $value): string|int|float
    {
        return \is_int($value) || \is_float($value) ? $value : (string) $value;
    }

    private static function encrypt(mixed $value): string
    {
        $crypt = \Tavaan\Core\Facade\Facade::getFacadeApplication()->make('encrypter');

        return $crypt->encrypt(\is_string($value) ? $value : (string) \json_encode($value, JSON_THROW_ON_ERROR), 'orm-cast');
    }

    private static function decrypt(mixed $value): mixed
    {
        $crypt = \Tavaan\Core\Facade\Facade::getFacadeApplication()->make('encrypter');
        $plain = $crypt->decrypt((string) $value, 'orm-cast');
        $decoded = \json_decode($plain, true);

        return \is_array($decoded) && !\array_is_list($decoded) ? $decoded : $plain;
    }

    private static function hash(mixed $value): string
    {
        $plain = (string) $value;

        // اگر قبلاً hash شده، دوباره hash نکن (idempotent)
        if (\preg_match('/^\$(argon2id|argon2i|2y)\$/', $plain) === 1) {
            return $plain;
        }

        $hasher = \Tavaan\Core\Facade\Facade::getFacadeApplication()->bound('hash')
            ? \Tavaan\Core\Facade\Facade::getFacadeApplication()->make('hash')
            : null;

        if ($hasher !== null) {
            return $hasher->make($plain);
        }

        // fallback تا قبل از M4 (موتور hash)
        return \password_hash($plain, \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }
}
