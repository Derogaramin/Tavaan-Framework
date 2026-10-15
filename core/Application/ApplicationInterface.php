<?php

declare(strict_types=1);

namespace Tavaan\Core\Application;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Core
 * ApplicationInterface — قرارداد اپلیکیشن (DOC-027 §8).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
interface ApplicationInterface extends ContainerInterface
{
    public function basePath(string $path = ''): string;

    public function storagePath(string $path = ''): string;

    public function configPath(string $path = ''): string;

    public function version(): string;

    public function runningInConsole(): bool;

    public function runningUnitTests(): bool;

    public function environment(): string;

    public function isProduction(): bool;

    public function isLocal(): bool;

    public function mode(): RuntimeMode;

    public function setMode(RuntimeMode $mode): void;

    /** میلی‌ثانیه از شروع بوت. */
    public function bootTimeMs(): float;

    /** کارهای پایانی: provider shutdown + flush لاگ. */
    public function terminate(): void;
}
