<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Testing;

use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Core\Contracts\Log\LoggerInterface;

/**
 * تَوان | Tavaan — Database
 * RecordingLogger — Fake لاگر برای تست (ضبط پیام‌ها — DOC-014 §4).
 *
 * @api internal
 * @see docs/07-quality/testing-strategy.md
 */
final class RecordingLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function emergency(string $message, array|\Closure $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function alert(string $message, array|\Closure $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function critical(string $message, array|\Closure $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function error(string $message, array|\Closure $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function warning(string $message, array|\Closure $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function notice(string $message, array|\Closure $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function info(string $message, array|\Closure $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function debug(string $message, array|\Closure $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed>|\Closure(): array<string, mixed> $context */
    public function log(LogLevel|string $level, string $message, array|\Closure $context = []): void
    {
        $resolved = $context instanceof \Closure ? $context() : $context;
        $this->records[] = [
            'level'   => $level instanceof LogLevel ? $level->value : $level,
            'message' => $message,
            'context' => $resolved,
        ];
    }

    /** @return list<array{level: string, message: string, context: array<string, mixed>}> */
    public function records(): array
    {
        return $this->records;
    }

    public function hasRecorded(string $messageId): bool
    {
        foreach ($this->records as $record) {
            if ($record['message'] === $messageId) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        $this->records = [];
    }
}
