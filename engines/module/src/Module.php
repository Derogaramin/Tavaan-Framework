<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

/**
 * تَوان | Tavaan — Module
 * Module — یک ماژول/موتور (manifest + وضعیت).
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class Module
{
    public function __construct(
        private readonly Manifest $manifest,
        private bool $enabled = true,
    ) {
    }

    public function id(): string
    {
        return $this->manifest->id;
    }

    public function name(): string
    {
        return $this->manifest->name;
    }

    public function version(): string
    {
        return $this->manifest->version;
    }

    public function type(): string
    {
        return $this->manifest->type;
    }

    public function path(): string
    {
        return $this->manifest->path;
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    /** @return list<string> وابستگی‌های موتور/ماژول (شناسهٔ کوتاه) */
    public function dependencies(): array
    {
        return $this->manifest->engineDependencies();
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
