<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Core\Contracts\Config\EnvRepositoryInterface;
use Tavaan\Engine\Config\Exceptions\MissingConfigKeyException;

/**
 * تَوان | Tavaan — Config
 * EnvRepository — منبع یگانهٔ متغیرهای محیطی (بدون putenv — E2).
 *
 * اولویت (E1): `$_ENV`/`$_SERVER` → `.env` → default.
 * بارگذاری `.env` به‌صورت تنبل و یک‌بار انجام می‌شود (idempotent).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class EnvRepository implements EnvRepositoryInterface
{
    /** @var array<string, string|bool|int|float|null> */
    private array $vars = [];

    private bool $loaded = false;

    private static ?self $instance = null;

    /**
     * @param list<string> $files فایل‌های .env به ترتیب اولویت (اول = بالاتر)
     */
    public function __construct(
        private readonly array $files = [],
        private readonly EnvLoader $loader = new EnvLoader(),
    ) {
    }

    /** نمونهٔ سراسری (برای helper `env()`). */
    public static function instance(): self
    {
        if (self::$instance === null) {
            $files = [];
            if (\defined('TVN_BASE_PATH')) {
                $base = (string) \TVN_BASE_PATH;
                $envName = $_SERVER['TVN_ENV_FILE'] ?? $_ENV['TVN_ENV_FILE'] ?? null;
                if (\is_string($envName) && $envName !== '') {
                    $files[] = \str_starts_with($envName, '/') ? $envName : $base . '/' . $envName;
                } else {
                    $files[] = $base . '/.env';
                    $files[] = $base . '/.env.local';
                }
            }
            self::$instance = new self($files);
        }

        return self::$instance;
    }

    /** جایگزینی نمونهٔ سراسری (تست). */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /** بارگذاری تنبل .env (idempotent). */
    public function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        // اولویت معکوس: فایل آخر اول پر می‌شود تا فایل اول override کند
        foreach (\array_reverse($this->files) as $file) {
            foreach ($this->loader->load($file) as $key => $value) {
                $this->vars[$key] = $value;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();

        // E1: $_ENV/$_SERVER بالاترین اولویت
        if (isset($_ENV[$key]) && (\is_string($_ENV[$key]) || \is_bool($_ENV[$key]) || \is_int($_ENV[$key]))) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && \is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return \array_key_exists($key, $this->vars) ? $this->vars[$key] : $default;
    }

    public function has(string $key): bool
    {
        $this->ensureLoaded();

        return isset($_ENV[$key]) || isset($_SERVER[$key]) || \array_key_exists($key, $this->vars);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return \is_scalar($value) ? (string) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return match (\strtolower($value)) {
                'true', '(true)', '1', 'yes', 'on' => true,
                'false', '(false)', '0', 'no', 'off', '' => false,
                default => $default,
            };
        }

        return (bool) $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return \is_numeric($value) || \is_bool($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return \is_numeric($value) ? (float) $value : $default;
    }

    /** @return list<string> */
    public function list(string $key, string $separator = ','): array
    {
        $value = $this->get($key);
        if (!\is_string($value) || $value === '') {
            return [];
        }

        return \array_values(\array_filter(\array_map(\trim(...), \explode($separator, $value)), static fn (string $s): bool => $s !== ''));
    }

    /** @return array<string, mixed>|null */
    public function json(string $key): ?array
    {
        $value = $this->get($key);
        if (!\is_string($value) || $value === '') {
            return null;
        }

        $decoded = \json_decode($value, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /** @param list<string> $keys */
    public function required(array $keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw MissingConfigKeyException::for($missing);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(bool $masked = false): array
    {
        $this->ensureLoaded();

        if (!$masked) {
            return $this->vars;
        }

        $out = [];
        foreach ($this->vars as $key => $value) {
            $out[$key] = $this->isSecretKey($key) ? '********' : $value;
        }

        return $out;
    }

    private function isSecretKey(string $key): bool
    {
        $lower = \strtolower($key);

        return \str_contains($lower, 'key') || \str_contains($lower, 'secret')
            || \str_contains($lower, 'password') || \str_contains($lower, 'token');
    }
}
