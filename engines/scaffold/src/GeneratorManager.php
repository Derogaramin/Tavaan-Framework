<?php

declare(strict_types=1);

namespace Tavaan\Engine\Scaffold;

/**
 * تَوان | Tavaan — Scaffold
 * GeneratorManager — رجیستری Generatorها و نقطهٔ ورود `generate` (DOC-077 §11).
 *
 * کلیدها همان بخش میانی دستور `make:<key>` هستند (model, controller, module, …).
 * ثبت به‌صورت تنبل (Closure) انجام می‌شود تا هزینهٔ بوت صفر بماند.
 *
 * @api 1.0
 * @see docs/03-engines/scaffold.md
 */
final class GeneratorManager
{
    /** @var array<string, Generator|\Closure(StubRenderer, string): Generator> */
    private array $generators = [];

    /** @var array<string, Generator> نمونه‌های resolve‌شده (یک‌بار ساخت) */
    private array $resolved = [];

    public function __construct(
        private readonly StubRenderer $stubs,
        private readonly string $basePath,
    ) {
    }

    /**
     * @param Generator|\Closure(StubRenderer, string): Generator $generator
     */
    public function register(string $key, Generator|\Closure $generator): void
    {
        $this->generators[$key] = $generator;
        unset($this->resolved[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->generators[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = \array_keys($this->generators);
        \sort($keys);

        return $keys;
    }

    /**
     * @throws ScaffoldException اگر کلید ثبت نشده باشد
     */
    public function get(string $key): Generator
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $registered = $this->generators[$key] ?? null;
        if ($registered === null) {
            throw ScaffoldException::generatorMissing($key);
        }

        $generator = $registered instanceof Generator
            ? $registered
            : $registered($this->stubs, $this->basePath);

        return $this->resolved[$key] = $generator;
    }

    /**
     * @return array<string, string> مسیر مطلق → محتوا
     *
     * @throws ScaffoldException
     */
    public function generate(string $key, GenerateCommand $cmd): array
    {
        return $this->get($key)->generate($cmd);
    }

    public function stubs(): StubRenderer
    {
        return $this->stubs;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
