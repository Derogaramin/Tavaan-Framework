<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

/**
 * تَوان | Tavaan — Queue
 * WorkerOptions — گزینه‌های اجرای Worker (DOC-061 §3/§4).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class WorkerOptions
{
    /**
     * @param string $connectionName  نام اتصال ('' = پیش‌فرض config)
     * @param string $queue           فهرست صف‌ها با اولویت — جداشده با ویرگول (مثل `high,default,low`)
     * @param int    $maxJobs         حداکثر job پیش از خروج (۰ = نامحدود)
     * @param int    $maxTime         حداکثر ثانیه پیش از خروج (۰ = نامحدود)
     * @param int    $maxMemory       حداکثر MB پیش از recycle (۰ = نامحدود)
     * @param float  $sleep           ثانیه خواب وقتی صف خالی است
     * @param int    $maxTries        تلاش پیش‌فرض jobها (۰ = نامحدود)
     * @param int    $timeout         timeout پیش‌فرض اجرا (ثانیه؛ ۰ = بدون)
     * @param int    $backoff         تأخیر پیش‌فرض retry (ثانیه)
     * @param bool   $stopWhenEmpty   خروج وقتی صف خالی شد
     * @param bool   $force           اجرا حتی در maintenance mode
     */
    public function __construct(
        public readonly string $connectionName = '',
        public readonly string $queue = 'default',
        public readonly int $maxJobs = 0,
        public readonly int $maxTime = 0,
        public readonly int $maxMemory = 0,
        public readonly float $sleep = 3.0,
        public readonly int $maxTries = 3,
        public readonly int $timeout = 60,
        public readonly int $backoff = 0,
        public readonly bool $stopWhenEmpty = false,
        public readonly bool $force = false,
    ) {
    }

    /**
     * ساخت از آرایه (کلیدهای docs: maxJobs/maxTime/sleep/…).
     *
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $queues = $options['queue'] ?? $options['queues'] ?? 'default';
        if (\is_array($queues)) {
            $queues = \implode(',', \array_map(strval(...), $queues));
        }

        return new self(
            connectionName: (string) ($options['connectionName'] ?? $options['connection'] ?? ''),
            queue: (string) $queues,
            maxJobs: (int) ($options['maxJobs'] ?? $options['max_jobs'] ?? 0),
            maxTime: (int) ($options['maxTime'] ?? $options['max_time'] ?? 0),
            maxMemory: (int) ($options['maxMemory'] ?? $options['memory'] ?? 0),
            sleep: (float) ($options['sleep'] ?? 3.0),
            maxTries: (int) ($options['maxTries'] ?? $options['tries'] ?? $options['max_tries'] ?? 3),
            timeout: (int) ($options['timeout'] ?? 60),
            backoff: (int) ($options['backoff'] ?? 0),
            stopWhenEmpty: (bool) ($options['stopWhenEmpty'] ?? $options['stop_when_empty'] ?? false),
            force: (bool) ($options['force'] ?? false),
        );
    }

    /** @return list<string> صف‌ها به‌ترتیب اولویت */
    public function queueNames(): array
    {
        $names = \array_map(
            static fn (string $q): string => \trim($q),
            \explode(',', $this->queue),
        );
        $names = \array_values(\array_filter($names, static fn (string $q): bool => $q !== ''));

        return $names === [] ? ['default'] : $names;
    }
}
