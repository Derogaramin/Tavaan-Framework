<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\I18n\TranslatorInterface;

/**
 * تَوان | Tavaan — Validation
 * ValidatorFactory — ساخت Validator با تزریق translator/db/config (Facade: Validator).
 *
 * @api 1.0
 * @see docs/03-engines/validation.md
 */
final class ValidatorFactory
{
    /** @var array<string, array{0: \Closure, 1: ?string}> قواعد سراسری ثبت‌شده */
    private array $globalExtensions = [];

    /**
     * @param ConnectionInterface|ContainerInterface|null $db اتصال یا کانتینر (resolving تنبل)
     */
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly ConnectionInterface|ContainerInterface|null $db = null,
        private readonly ?RepositoryInterface $config = null,
    ) {
    }

    /** اتصال دیتابیس (تنبل — فقط برای قواعد unique/exists). */
    private function connection(): ?ConnectionInterface
    {
        if ($this->db instanceof ConnectionInterface) {
            return $this->db;
        }
        if ($this->db instanceof ContainerInterface && $this->db->bound(ConnectionInterface::class)) {
            $connection = $this->db->make(ConnectionInterface::class);

            return $connection instanceof ConnectionInterface ? $connection : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed>              $data
     * @param array<string, string|list<mixed>> $rules
     * @param array<string, string>             $messages
     * @param array<string, string>             $attributes
     */
    public function make(array $data, array $rules, array $messages = [], array $attributes = []): Validator
    {
        $config = (array) ($this->config?->get('validation', []) ?? []);

        $validator = new Validator($data, $rules, $messages, $attributes, $this->translator, $this->connection(), $config);

        foreach ($this->globalExtensions as $rule => [$callback, $message]) {
            $validator->extend($rule, $callback, $message);
        }

        return $validator;
    }

    /**
     * میان‌بر: validate یا ValidationException.
     *
     * @param array<string, mixed>              $data
     * @param array<string, string|list<mixed>> $rules
     * @param array<string, string>             $messages
     * @param array<string, string>             $attributes
     *
     * @return array<string, mixed>
     */
    public function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return $this->make($data, $rules, $messages, $attributes)->validate();
    }

    /**
     * ثبت قاعدهٔ سفارشی سراسری.
     *
     * @param callable(string, mixed, array<int, string>, \Closure(string): void): bool|void $extension
     */
    public function extend(string $rule, callable $extension, ?string $message = null): void
    {
        $closure = $extension instanceof \Closure ? $extension : \Closure::fromCallable($extension);
        $this->globalExtensions[\strtolower($rule)] = [$closure, $message];
    }

    /** جای‌گذار پیام (replacer) — نگهداری سازگاری API (DOC-051 §14). */
    public function replacer(string $rule, callable $replacer): void
    {
        // جای‌گذاری‌ها در Validator::replacePlaceholders انجام می‌شود؛ این قلاب برای افزونه‌هاست
        $this->globalExtensions['replacer:' . \strtolower($rule)] = [
            $replacer instanceof \Closure ? $replacer : \Closure::fromCallable($replacer),
            null,
        ];
    }

    /**
     * اعتبارسنجی Attribute-based روی DTO (DOC-051 §6).
     *
     * @param class-string          $class
     * @param array<string, mixed>  $data
     */
    public function forDto(string $class, array $data): DtoValidator
    {
        return new DtoValidator($this, $class, $data);
    }
}
