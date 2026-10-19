<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestFailure — رکورد یک شکست/خطای تست برای گزارش (نام، پیام، محل، نوع).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final readonly class TestFailure
{
    /**
     * @param string $test     نام کامل (`Class::method`)
     * @param string $message  پیام خطا
     * @param string $location محل وقوع (`file:line` — خالی اگر نامعلوم)
     * @param string $kind     `failure` (Assertion) یا `error` (Throwable دیگر)
     * @param string $exception کلاس Throwable (برای errorها)
     */
    public function __construct(
        public string $test,
        public string $message,
        public string $location,
        public string $kind = 'failure',
        public string $exception = '',
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'test' => $this->test,
            'message' => $this->message,
            'location' => $this->location,
            'kind' => $this->kind,
            'exception' => $this->exception,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            test: (string) ($data['test'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            location: (string) ($data['location'] ?? ''),
            kind: (string) ($data['kind'] ?? 'failure'),
            exception: (string) ($data['exception'] ?? ''),
        );
    }
}
