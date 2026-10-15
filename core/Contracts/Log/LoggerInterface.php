<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Log;

/**
 * قرارداد Logger (PSR-3-like داخلی، بدون پکیج psr/log).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
interface LoggerInterface
{
    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function emergency(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function alert(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function critical(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function error(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function warning(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function notice(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function info(string $message, array|\Closure $context = []): void;

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function debug(string $message, array|\Closure $context = []): void;

    /**
     * @param array<string, mixed>|\Closure(): array<string, mixed> $context
     */
    public function log(LogLevel|string $level, string $message, array|\Closure $context = []): void;
}
