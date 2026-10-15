<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — Config
 * helpers.php — توابع env() (فقط در فایل‌های config/ مجاز — DOC-027 §5.2).
 */

use Tavaan\Engine\Config\EnvRepository;

if (!\function_exists('env')) {
    /**
     * خواندن متغیر محیطی (بدون putenv).
     */
    function env(string $key, mixed $default = null): mixed
    {
        return EnvRepository::instance()->get($key, $default);
    }
}

if (!\function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        return EnvRepository::instance()->bool($key, $default);
    }
}

if (!\function_exists('env_int')) {
    function env_int(string $key, int $default = 0): int
    {
        return EnvRepository::instance()->int($key, $default);
    }
}

if (!\function_exists('env_string')) {
    function env_string(string $key, string $default = ''): string
    {
        return EnvRepository::instance()->string($key, $default);
    }
}

if (!\function_exists('env_float')) {
    function env_float(string $key, float $default = 0.0): float
    {
        return EnvRepository::instance()->float($key, $default);
    }
}

if (!\function_exists('env_list')) {
    /** @return list<string> */
    function env_list(string $key, string $separator = ','): array
    {
        return EnvRepository::instance()->list($key, $separator);
    }
}
