<?php

declare(strict_types=1);

namespace Tavaan\Engine\Error;

use Tavaan\Core\Exceptions\Auth\AuthenticationException;
use Tavaan\Core\Exceptions\Authorization\AuthorizationException;
use Tavaan\Core\Exceptions\Http\CsrfTokenMismatchException;
use Tavaan\Core\Exceptions\Http\HttpException;
use Tavaan\Core\Exceptions\Http\MethodNotAllowedException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Http\TooManyRequestsException;
use Tavaan\Core\Exceptions\Validation\ValidationException;

/**
 * تَوان | Tavaan — Error
 * ExceptionMapper — نگاشت Throwable → وضعیت HTTP با «طولانی‌ترین تطبیق سلسله‌مراتبی».
 *
 * @api 1.0
 * @see docs/03-engines/error.md
 */
final class ExceptionMapper
{
    /** @var array<class-string, int> */
    private const DEFAULT_MAP = [
        ValidationException::class        => 422,
        AuthenticationException::class    => 401,
        AuthorizationException::class     => 403,
        RouteNotFoundException::class     => 404,
        MethodNotAllowedException::class  => 405,
        CsrfTokenMismatchException::class => 419,
        TooManyRequestsException::class   => 429,
    ];

    /** @var array<class-string, int> */
    private array $map = self::DEFAULT_MAP;

    /** @var array<class-string, \Closure(\Throwable): \Throwable> */
    private array $mappers = [];

    /** افزودن/override نگاشت. */
    public function map(string $exceptionClass, int|\Closure $to): void
    {
        if ($to instanceof \Closure) {
            $this->mappers[$exceptionClass] = $to;

            return;
        }

        $this->map[$exceptionClass] = $to;
    }

    /** اعمال mapperهای ثبت‌شده (تبدیل استثنا). */
    public function prepare(\Throwable $e): \Throwable
    {
        foreach ($this->mappers as $class => $mapper) {
            if ($e instanceof $class) {
                $e = $mapper($e);
            }
        }

        return $e;
    }

    /** وضعیت HTTP برای استثنا (سلسله‌مراتبی: نزدیک‌ترین والد). */
    public function statusFor(\Throwable $e): int
    {
        if ($e instanceof HttpException) {
            return $e->statusCode;
        }

        foreach ([$e::class, ...\array_values(\class_parents($e) ?: []), ...\array_keys(\class_implements($e) ?: [])] as $candidate) {
            if (isset($this->map[$candidate])) {
                return $this->map[$candidate];
            }
        }

        return 500;
    }
}
