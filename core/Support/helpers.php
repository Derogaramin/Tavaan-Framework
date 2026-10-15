<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Support
 * helpers.php — توابع سراسری محدود و کنترل‌شده (DOC-080).
 *
 * 🔒 تنها توابعی که در همهٔ لایه‌ها لازم‌اند اینجا تعریف می‌شوند.
 * این فایل از طریق autoload `files` بارگذاری می‌شود (بدون require دستی).
 */

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facade;

if (!\function_exists('app')) {
    /**
     * دسترسی به کانتینر/اپلیکیشن؛ با آرگومان = make.
     *
     * @template T
     * @param class-string<T>|string|null $abstract
     * @param array<string, mixed>        $parameters
     *
     * @return T|mixed|ContainerInterface
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Facade::getFacadeApplication();
        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!\function_exists('base_path')) {
    /** مسیر ریشهٔ پروژه (یا زیرمسیر آن). */
    function base_path(string $path = ''): string
    {
        $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 2);

        return $path === '' ? $base : $base . '/' . \ltrim($path, '/');
    }
}

if (!\function_exists('storage_path')) {
    /** مسیر پوشهٔ storage (یا زیرمسیر آن). */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . \ltrim($path, '/')));
    }
}

if (!\function_exists('config_path')) {
    /** مسیر پوشهٔ config (یا فایل خاص). */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path === '' ? '' : '/' . \ltrim($path, '/')));
    }
}

if (!\function_exists('config')) {
    /**
     * خواندن (یا نوشتن در dev) پیکربندی با dot-notation.
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $repository = app('config');

        if ($key === null) {
            return $repository;
        }
        if (\is_array($key)) {
            $repository->set($key);

            return null;
        }

        return $repository->get($key, $default);
    }
}

if (!\function_exists('value')) {
    /**
     * مقداردهی یک مقدار یا Closure (تنبل).
     *
     * @template TValue
     * @param TValue|\Closure(): TValue $value
     *
     * @return TValue
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!\function_exists('dd')) {
    /**
     * dump + die — فقط برای توسعه.
     */
    function dd(mixed ...$values): never
    {
        foreach ($values as $value) {
            \var_dump($value);
        }
        exit(1);
    }
}
