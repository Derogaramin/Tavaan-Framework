<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Error\EarlyErrorManager;

/**
 * تَوان | Tavaan — Error
 * ErrorHandler — ثبت handlerهای سراسری PHP (error/exception/shutdown).
 *
 * - `E_*` → ErrorException (Fail Fast)
 * - 🔒 E_DEPRECATED: لاگ در کانال deprecations + Failure در CI (NFR-QUA-01)
 * - Fatal در shutdown → رندر امن
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class ErrorHandler
{
    private static ?self $instance = null;

    private int $deprecationCount = 0;

    /** ذخیرهٔ حافظه برای رندر خطای OOM (DOC-035 §6) */
    private ?string $memoryReserve = null;

    public function __construct(
        private readonly ExceptionHandler $handler,
        private readonly string $environment = 'production',
        private readonly bool $failOnDeprecation = false,
        private readonly int $maxDeprecationsPerRequest = 10,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** ثبت سراسری (idempotent). */
    public static function register(
        ExceptionHandler $handler,
        string $environment = 'production',
        bool $failOnDeprecation = false,
        ?LoggerInterface $logger = null,
    ): self {
        EarlyErrorManager::unregister();

        $instance = new self($handler, $environment, $failOnDeprecation, logger: $logger);
        self::$instance = $instance;

        \error_reporting(E_ALL);
        \ini_set('display_errors', '0');
        \set_error_handler([$instance, 'handleError']);
        \set_exception_handler([$instance, 'handleException']);
        \register_shutdown_function([$instance, 'handleShutdown']);

        // ذخیرهٔ ۲۵۶KB برای رندر خطای OOM
        $instance->memoryReserve = \str_repeat('x', 262_144);

        return $instance;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    public static function unregister(): void
    {
        if (self::$instance !== null) {
            \restore_error_handler();
            \restore_exception_handler();
            self::$instance = null;
        }
    }

    /**
     * تبدیل E_* به ErrorException.
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((\error_reporting() & $severity) === 0) {
            return false;
        }

        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            return $this->handleDeprecation($message, $file, $line);
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * رندر و خروج برای استثناهای مدیریت‌نشده.
     */
    public function handleException(\Throwable $e): void
    {
        $result = $this->handler->handle($e, PHP_SAPI === 'cli' ? 'cli' : 'auto');

        if (PHP_SAPI === 'cli') {
            \fwrite(STDERR, $result->body);
            exit(1);
        }

        $result->send();
        exit($result->status >= 500 ? 1 : 0);
    }

    /**
     * خطاهای Fatal در shutdown.
     */
    public function handleShutdown(): void
    {
        $error = \error_get_last();
        if ($error === null || !$this->isFatal($error['type'])) {
            return;
        }

        // آزادسازی ذخیرهٔ حافظه برای رندر OOM
        $this->memoryReserve = null;

        $e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        try {
            $result = $this->handler->render($e, PHP_SAPI === 'cli' ? 'cli' : 'auto');
            $this->logger?->critical('error.fatal_shutdown', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            if (PHP_SAPI === 'cli') {
                \fwrite(STDERR, $result->body);
            } else {
                $result->send();
            }
        } catch (\Throwable) {
            if (!\headers_sent()) {
                \http_response_code(500);
            }
        }
    }

    /**
     * 🔒 Deprecation: لاگ + شکست در CI.
     */
    private function handleDeprecation(string $message, string $file, int $line): bool
    {
        $this->deprecationCount++;

        if ($this->deprecationCount <= $this->maxDeprecationsPerRequest) {
            $this->logger?->warning('deprecation.detected', [
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);
        }

        if ($this->failOnDeprecation) {
            // در CI: تبدیل به استثنا → Failure (AC-ERR-09)
            throw new \ErrorException('Deprecation: ' . $message, 0, E_USER_DEPRECATED, $file, $line);
        }

        return true;
    }

    private function isFatal(int $type): bool
    {
        return \in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }

    /** تعداد deprecationهای این درخواست (برای CI summary). */
    public function deprecationCount(): int
    {
        return $this->deprecationCount;
    }
}
