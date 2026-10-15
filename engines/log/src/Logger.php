<?php

declare(strict_types=1);

namespace Tavaan\Engine\Log;

use Tavaan\Core\Contracts\Log\FormatterInterface;
use Tavaan\Core\Contracts\Log\HandlerInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Log\LogLevel;
use Tavaan\Core\Contracts\Log\ProcessorInterface;
use Tavaan\Core\Context\TraceContext;
use Tavaan\Engine\Log\Formatters\LineFormatter;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Log
 * Logger — یک کانال لاگ: سطح آستانه، Processorها، Formatter، Handlerها و Buffer.
 *
 * 🔒 Log Injection: پیام/context با حذف CRLF پاک‌سازی می‌شود.
 * 🔒 Context تنبل: `Closure` فقط در صورت عبور از آستانهٔ سطح اجرا می‌شود.
 * 🔒 per_request_limit: پس از عبور از حد، فقط یک هشدار (جلوگیری از Flooding).
 *
 * @api 1.0
 * @see docs/03-engines/log.md
 */
final class Logger implements LoggerInterface
{
    /** @var int شمارندهٔ سراسری درخواست جاری */
    private static int $written = 0;

    private static bool $limitWarned = false;

    private FormatterInterface $formatter;

    /** @var list<ProcessorInterface> */
    private array $processors;

    /** @var array<string, mixed> context اشتراکی (کل درخواست) */
    private array $sharedContext = [];

    /** @var array<string, mixed> context یک‌بار مصرف */
    private array $onceContext = [];

    private ?Buffer $buffer = null;

    /**
     * @param list<HandlerInterface>    $handlers
     * @param list<ProcessorInterface>  $processors
     * @param array<string, mixed>      $limits
     */
    public function __construct(
        private readonly string $channelName,
        private readonly LogLevel $threshold = LogLevel::Debug,
        private readonly array $handlers = [],
        array $processors = [],
        ?FormatterInterface $formatter = null,
        private readonly array $limits = [],
        private readonly string $environment = 'production',
        bool $buffered = false,
        int $bufferMaxLines = 50,
    ) {
        $this->processors = $processors;
        $this->formatter = $formatter ?? new LineFormatter();

        if ($buffered && $this->handlers !== []) {
            $this->buffer = new Buffer($this->handlers[0], $bufferMaxLines);
        }
    }

    // ─── API سطح‌ها ───────────────────────────────────────────────────────

    public function emergency(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    public function alert(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Alert, $message, $context);
    }

    public function critical(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Critical, $message, $context);
    }

    public function error(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    public function warning(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Warning, $message, $context);
    }

    public function notice(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Notice, $message, $context);
    }

    public function info(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    public function debug(string $message, array|\Closure $context = []): void
    {
        $this->log(LogLevel::Debug, $message, $context);
    }

    public function log(LogLevel|string $level, string $message, array|\Closure $context = []): void
    {
        $level = $level instanceof LogLevel ? $level : LogLevel::fromName($level);

        if (!$level->withinThreshold($this->threshold)) {
            return;
        }

        $maxPerRequest = (int) ($this->limits['per_request_limit'] ?? 500);
        if (self::$written >= $maxPerRequest) {
            if (!self::$limitWarned) {
                self::$limitWarned = true;
                $this->writeRecord(LogLevel::Warning, 'log.per_request_limit_exceeded', ['limit' => $maxPerRequest], skipProcessors: true);
            }

            return;
        }

        // Context تنبل — فقط حالا که لاگ قطعی است
        $contextArray = $context instanceof \Closure ? (array) $context() : $context;

        $this->writeRecord($level, $message, $contextArray);
        self::$written++;
    }

    // ─── Context تجمعی ────────────────────────────────────────────────────

    /** context برای تمام رکوردهای این کانال (کل درخواست). @param array<string, mixed> $context */
    public function shareContext(array $context): void
    {
        $this->sharedContext = [...$this->sharedContext, ...$context];
    }

    /** نسخهٔ یک‌بار مصرف (clone با context اضافی). @param array<string, mixed> $context */
    public function withContext(array $context): self
    {
        $clone = clone $this;
        $clone->onceContext = [...$this->sharedContext, ...$context];

        return $clone;
    }

    public function withoutContext(): void
    {
        $this->sharedContext = [];
    }

    /** @return array<string, mixed> */
    public function sharedContext(): array
    {
        return $this->sharedContext;
    }

    public function name(): string
    {
        return $this->channelName;
    }

    public function flush(): void
    {
        $this->buffer?->flush();
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }

    /** ریست شمارندهٔ درخواست (شروع درخواست جدید در Resident/تست). */
    public static function resetRequestCounter(): void
    {
        self::$written = 0;
        self::$limitWarned = false;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $context
     */
    private function writeRecord(LogLevel $level, string $message, array $context, bool $skipProcessors = false): void
    {
        $context = [...$this->sharedContext, ...$this->onceContext, ...$context];
        $this->onceContext = [];

        $record = new LogRecord(
            channel: $this->channelName,
            level: $level,
            message: $this->sanitize($message),
            context: $this->sanitizeContext($context),
            severity: $level->severity(),
            timestamp: Clock::isoNow(),
            traceId: TraceContext::traceId(),
            requestId: TraceContext::requestId(),
            environment: $this->environment,
        );

        if (!$skipProcessors) {
            foreach ($this->processors as $processor) {
                $record = $processor->process($record);
            }
        }

        $line = $this->formatter->format($record);

        try {
            if ($this->buffer !== null) {
                $this->buffer->write($line);

                return;
            }
            foreach ($this->handlers as $handler) {
                $handler->write($line);
            }
        } catch (\Throwable $e) {
            // 🔒 AC-LOG-05: خطای لاگ هرگز برنامه را Crash نمی‌کند
            \fwrite(STDERR, '[tavaan-log] write failed: ' . $e->getMessage() . PHP_EOL);
        }
    }

    /** 🔒 Log Injection: حذف CR/LF/TAB/NUL. */
    private function sanitize(string $s): string
    {
        return \preg_replace('/[\r\n\t\0]+/', ' ', $s) ?? $s;
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $maxBytes = (int) ($this->limits['max_context_bytes'] ?? 8192);
        $out = [];
        foreach ($context as $k => $v) {
            if (\is_string($v)) {
                $v = $this->sanitize($v);
                if (\strlen($v) > $maxBytes) {
                    $v = \substr($v, 0, $maxBytes) . '…[truncated]';
                }
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
