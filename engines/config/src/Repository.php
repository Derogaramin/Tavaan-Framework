<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Engine\Config\Exceptions\ConfigFrozenException;
use Tavaan\Engine\Config\Exceptions\MissingConfigKeyException;
use Tavaan\Support\Arr;

/**
 * تَوان | Tavaan — Config
 * Repository — مخزن پیکربندی با dot-notation، LRU، Freeze و Masking.
 *
 * 🔒 در PROD با `freeze()` فقط‌خواندنی می‌شود (AC-CFG-03).
 * کلیدهای حساس در `toJson()`/`masked()` به‌صورت خودکار mask می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class Repository implements RepositoryInterface
{
    /** حداکثر ورودی کش LRU کلیدهای پرتکرار */
    private const LRU_LIMIT = 500;

    /** @var array<string, mixed> */
    private array $items = [];

    /** @var array<string, mixed> کش LRU (get) */
    private array $lru = [];

    private bool $frozen = false;

    /**
     * @param array<string, mixed> $items
     * @param list<string>         $secretPatterns الگوهای glob (مانند `*.password`)
     */
    public function __construct(
        array $items = [],
        private readonly array $secretPatterns = [
            '*.password', '*.secret', '*.key', '*.token', 'db.*.password', 'mail.*.password',
        ],
    ) {
        $this->items = $items;
    }

    // ─── خواندن ───────────────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        if (\array_key_exists($key, $this->lru)) {
            return $this->lru[$key];
        }

        // 🔒 فقط مقادیر «موجود» کش می‌شوند تا defaultهای مختلف خراب نشوند
        $value = Arr::get($this->items, $key, self::notFoundSentinel());
        if ($value === self::notFoundSentinel()) {
            return $default;
        }

        if (\count($this->lru) >= self::LRU_LIMIT) {
            $this->lru = [];
        }
        $this->lru[$key] = $value;

        return $value;
    }

    /** sentinel داخلی برای تفکیک «مقدار null» از «کلید ناموجود». */
    private static function notFoundSentinel(): object
    {
        static $sentinel;

        return $sentinel ??= new \stdClass();
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                Arr::set($out, $key, $this->get($key));
            }
        }

        return $out;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $all = $this->items;
        foreach ($keys as $key) {
            Arr::forget($all, $key);
        }

        return $all;
    }

    /** کل یک namespace. @return array<string, mixed> */
    public function namespace(string $ns): array
    {
        $value = $this->get($ns, []);

        return \is_array($value) ? $value : [];
    }

    // ─── نوشتن ────────────────────────────────────────────────────────────

    public function set(array|string $key, mixed $value = null): void
    {
        if (\is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set((string) $k, $v);
            }

            return;
        }

        $this->assertNotFrozen($key);
        Arr::set($this->items, $key, $value);
        $this->lru = [];
    }

    public function forget(string $key): void
    {
        $this->assertNotFrozen($key);
        Arr::forget($this->items, $key);
        $this->lru = [];
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function push(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array = \is_array($array) ? $array : [];
        $array[] = $value;
        $this->set($key, $array);
    }

    public function prepend(string $key, mixed $value): void
    {
        $array = $this->get($key, []);
        $array = \is_array($array) ? $array : [];
        \array_unshift($array, $value);
        $this->set($key, $array);
    }

    // ─── Freeze ───────────────────────────────────────────────────────────

    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    // ─── Masking / خروجی ──────────────────────────────────────────────────

    /** نسخهٔ mask‌شدهٔ کل درخت (AC-CFG-04). @return array<string, mixed> */
    public function masked(): array
    {
        return Arr::maskRecursive($this->items, $this->secretPatterns);
    }

    public function toJson(bool $maskSecrets = true): string
    {
        $data = $maskSecrets ? $this->masked() : $this->items;

        return (string) \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $keys */
    public function requiredKeys(array $keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!$this->has($key) || $this->get($key) === null || $this->get($key) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw MissingConfigKeyException::for($missing);
        }
    }

    /** ادغام یک namespace کامل (توسط ConfigLoader). @param array<string, mixed> $values */
    public function mergeNamespace(string $ns, array $values): void
    {
        $existing = $this->items[$ns] ?? [];
        $this->items[$ns] = \is_array($existing) ? Arr::mergeDeep($existing, $values) : $values;
        $this->lru = [];
    }

    private function assertNotFrozen(string $key): void
    {
        if ($this->frozen) {
            throw ConfigFrozenException::for($key);
        }
    }
}
