<?php

declare(strict_types=1);

namespace Tavaan\Core\Error;

/**
 * تَوان | Tavaan — Core
 * EarlyErrorManager — مدیریت خطاهای پیش از بوت کامل (Bootstrap phase).
 *
 * از لحظهٔ require شدن `core/bootstrap.php` تا ثبت `ErrorHandler` کامل،
 * خطاها را به ErrorException تبدیل و در حافظه نگه می‌دارد تا بعداً گزارش شوند.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class EarlyErrorManager
{
    /** @var list<\Throwable> خطاهای رخ‌داده پیش از بوت کامل */
    private static array $earlyErrors = [];

    private static bool $registered = false;

    /** @var callable|null */
    private static $previousExceptionHandler = null;

    private function __construct()
    {
    }

    /** ثبت handlerهای موقت (idempotent). */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        \error_reporting(E_ALL);
        \set_error_handler(self::handleError(...));
        self::$previousExceptionHandler = \set_exception_handler(self::handleException(...));
        \register_shutdown_function(self::handleShutdown(...));

        self::$registered = true;
    }

    /** لغو ثبت handlerهای موقت (پس از ثبت ErrorHandler کامل). */
    public static function unregister(): void
    {
        if (!self::$registered) {
            return;
        }

        \restore_error_handler();
        \restore_exception_handler();
        self::$registered = false;
    }

    /** @return list<\Throwable> خطاهای ثبت‌شده تا کنون (برای replay پس از بوت) */
    public static function drain(): array
    {
        $errors = self::$earlyErrors;
        self::$earlyErrors = [];

        return $errors;
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((\error_reporting() & $severity) === 0) {
            return false;
        }

        $exception = new \ErrorException($message, 0, $severity, $file, $line);

        if (self::$previousExceptionHandler !== null) {
            self::$earlyErrors[] = $exception;
            return true;
        }

        self::$earlyErrors[] = $exception;
        self::writeEmergency($exception);

        return true;
    }

    public static function handleException(\Throwable $e): void
    {
        self::$earlyErrors[] = $e;
        self::writeEmergency($e);
    }

    public static function handleShutdown(): void
    {
        if (!self::$registered) {
            return;   // handler کامل ثبت شده — Early دیگر فعال نیست
        }

        /** @var array{type:int,message:string,file:string,line:int}|null $error */
        $error = \error_get_last();
        if ($error === null || !self::isFatal($error['type'])) {
            return;
        }

        $e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        self::$earlyErrors[] = $e;
        self::writeEmergency($e);
    }

    private static function isFatal(int $type): bool
    {
        return \in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }

    private static function writeEmergency(\Throwable $e): void
    {
        $line = \sprintf(
            '[tavaan-early] %s: %s in %s:%d%s',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
        );

        if (PHP_SAPI === 'cli') {
            \fwrite(STDERR, $line);
            return;
        }

        if (!\headers_sent()) {
            \http_response_code(500);
        }
        \fwrite(STDERR, $line);
    }
}
