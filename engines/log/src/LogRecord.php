<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log;

use Tavaan\Core\Contracts\Log\LogLevel;

/**
 * تَوان | Tavaan — Log
 * LogRecord — رکورد تغییرناپذیر یک لاگ (RFC 5424 + فیلدهای ردیابی).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final readonly class LogRecord
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $channel,
        public LogLevel $level,
        public string $message,
        public array $context = [],
        public int $severity = 6,
        public string $timestamp = '',
        public string $traceId = '',
        public string $spanId = '',
        public ?string $userId = null,
        public ?string $tenantId = null,
        public string $requestId = '',
        public string $environment = 'production',
        public ?string $file = null,
        public ?int $line = null,
        public int $memoryKb = 0,
        public float $runtimeMs = 0.0,
        public array $extra = [],
    ) {
    }

    public function withSource(?string $file, ?int $line): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            $this->context,
            $this->severity,
            $this->timestamp,
            $this->traceId,
            $this->spanId,
            $this->userId,
            $this->tenantId,
            $this->requestId,
            $this->environment,
            $file,
            $line,
            $this->memoryKb,
            $this->runtimeMs,
            $this->extra,
        );
    }

    public function withTrace(string $traceId, string $spanId): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            $this->context,
            $this->severity,
            $this->timestamp,
            $traceId,
            $spanId,
            $this->userId,
            $this->tenantId,
            $this->requestId,
            $this->environment,
            $this->file,
            $this->line,
            $this->memoryKb,
            $this->runtimeMs,
            $this->extra,
        );
    }

    public function withIdentity(?string $userId, ?string $tenantId): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            $this->context,
            $this->severity,
            $this->timestamp,
            $this->traceId,
            $this->spanId,
            $userId,
            $tenantId,
            $this->requestId,
            $this->environment,
            $this->file,
            $this->line,
            $this->memoryKb,
            $this->runtimeMs,
            $this->extra,
        );
    }

    /** @param array<string, mixed> $extra */
    public function withExtra(array $extra): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            $this->context,
            $this->severity,
            $this->timestamp,
            $this->traceId,
            $this->spanId,
            $this->userId,
            $this->tenantId,
            $this->requestId,
            $this->environment,
            $this->file,
            $this->line,
            $this->memoryKb,
            $this->runtimeMs,
            [...$this->extra, ...$extra],
        );
    }

    /** @param array<string, mixed> $context */
    public function withContext(array $context): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            [...$this->context, ...$context],
            $this->severity,
            $this->timestamp,
            $this->traceId,
            $this->spanId,
            $this->userId,
            $this->tenantId,
            $this->requestId,
            $this->environment,
            $this->file,
            $this->line,
            $this->memoryKb,
            $this->runtimeMs,
            $this->extra,
        );
    }

    public function withEnvironment(string $environment): self
    {
        return new self(
            $this->channel,
            $this->level,
            $this->message,
            $this->context,
            $this->severity,
            $this->timestamp,
            $this->traceId,
            $this->spanId,
            $this->userId,
            $this->tenantId,
            $this->requestId,
            $environment,
            $this->file,
            $this->line,
            $this->memoryKb,
            $this->runtimeMs,
            $this->extra,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ts' => $this->timestamp,
            'level' => $this->level->value,
            'severity' => $this->severity,
            'channel' => $this->channel,
            'message' => $this->message,
            'context' => $this->context,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'request_id' => $this->requestId,
            'env' => $this->environment,
            'file' => $this->file,
            'line' => $this->line,
            'memory_kb' => $this->memoryKb,
            'runtime_ms' => \round($this->runtimeMs, 3),
            ...$this->extra,
        ];
    }
}
