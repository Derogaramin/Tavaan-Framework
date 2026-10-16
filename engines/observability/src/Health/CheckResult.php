<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Health;

/**
 * تَوان | Tavaan — Observability
 * CheckResult — نتیجهٔ یک بررسی سلامتی (نام، وضعیت، زمان پاسخ، پیام).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class CheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $status,          // up|down
        public readonly float $responseTimeMs,
        public readonly string $message = '',
    ) {
    }

    public static function up(string $name, float $ms, string $message = ''): self
    {
        return new self($name, 'up', $ms, $message);
    }

    public static function down(string $name, float $ms, string $message = ''): self
    {
        return new self($name, 'down', $ms, $message);
    }

    public function isUp(): bool
    {
        return $this->status === 'up';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'name' => $this->name,
            'status' => $this->status,
            'responseTime' => \round($this->responseTimeMs, 2),
        ];
        if ($this->message !== '') {
            $out['message'] = $this->message;
        }

        return $out;
    }
}
