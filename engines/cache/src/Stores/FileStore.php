<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Cache
 * FileStore — درایور فایل با shard (بدون Redis) + add اتمیک با flock.
 *
 * 🔒 payload فقط JSON (Object Injection ممنوع — DOC-060 §8).
 * 🔒 کلید → hash (جلوگیری از path traversal / کلید بزرگ).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class FileStore implements StoreInterface
{
    private const DIR_MODE = 0775;

    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = '',
        private readonly int $permission = 0664,
    ) {
        $this->ensureDirectory($this->directory);
    }

    public function get(string $key): mixed
    {
        $payload = $this->readPayload($key);

        return $payload['value'] ?? null;
    }

    public function many(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        // نوشتن اتمیک tmp+rename (بدون read-modify-write → بدون نیاز به flock)
        $this->writePayloadAtomic($key, [
            'expires' => $ttl > 0 ? Clock::timestamp() + $ttl : 0,
            'value' => $value,
        ]);

        return true;
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->put((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->readPayload($key) !== null;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->writeWithLock($key, function ($handle) use ($value): int|false {
            $payload = $this->readPayloadFromHandle($handle);
            $current = (int) ($payload['value'] ?? 0);
            $new = $current + $value;
            $this->writePayloadToHandle($handle, [
                'expires' => $payload['expires'] ?? 0,
                'value' => $new,
            ]);

            return $new;
        });
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget(string $key): bool
    {
        $file = $this->path($key);
        if (\is_file($file)) {
            @\unlink($file);
        }

        return true;
    }

    public function flush(): bool
    {
        $this->clearDirectory($this->directory);

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** افزودن اتمیک (flock روی همان inode) فقط اگر کلید موجود/معتبر نباشد — مبنای Lock. */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->writeWithLock($key, function ($handle) use ($value, $ttl): bool {
            if ($this->readPayloadFromHandle($handle) !== null) {
                return false;
            }

            $this->writePayloadToHandle($handle, [
                'expires' => $ttl > 0 ? Clock::timestamp() + $ttl : 0,
                'value' => $value,
            ]);

            return true;
        });
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    /**
     * اجرای اتمیک با flock روی فایل کلید (callback روی همان handle می‌نویسد
     * تا inode قفل‌شده جایگزین نشود).
     *
     * @param \Closure(resource): mixed $callback
     */
    private function writeWithLock(string $key, \Closure $callback): mixed
    {
        $file = $this->path($key);
        $handle = \fopen($file, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!\flock($handle, LOCK_EX)) {
                return false;
            }

            return $callback($handle);
        } finally {
            \flock($handle, LOCK_UN);
            \fclose($handle);
        }
    }

    /** @return array{expires: int, value: mixed}|null */
    private function readPayload(string $key): ?array
    {
        $file = $this->path($key);
        if (!\is_file($file)) {
            return null;
        }

        $raw = @\file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        return $this->decode($raw, $file);
    }

    /**
     * خواندن از handle قفل‌شده.
     *
     * @param resource $handle
     *
     * @return array{expires: int, value: mixed}|null
     */
    private function readPayloadFromHandle($handle): ?array
    {
        \rewind($handle);
        $raw = (string) \stream_get_contents($handle);
        if ($raw === '') {
            return null;
        }

        return $this->decode($raw, null);
    }

    /**
     * نوشتن روی handle قفل‌شده (بدون rename — حفظ inode).
     *
     * @param resource $handle
     * @param array{expires: int, value: mixed} $payload
     */
    private function writePayloadToHandle($handle, array $payload): void
    {
        $json = (string) \json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        \ftruncate($handle, 0);
        \rewind($handle);
        \fwrite($handle, $json);
        \fflush($handle);
    }

    /** @return array{expires: int, value: mixed}|null */
    private function decode(string $raw, ?string $file): ?array
    {
        try {
            // 🔒 json_decode بدون allow_classes — Object Injection ممکن نیست
            $decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            if ($file !== null) {
                @\unlink($file);
            }

            return null;
        }

        if (!\is_array($decoded) || !\array_key_exists('value', $decoded)) {
            return null;
        }

        $expires = (int) ($decoded['expires'] ?? 0);
        if ($expires !== 0 && $expires <= Clock::timestamp()) {
            if ($file !== null) {
                @\unlink($file);
            }

            return null;
        }

        return ['expires' => $expires, 'value' => $decoded['value']];
    }

    /** @param array{expires: int, value: mixed} $payload */
    private function writePayloadAtomic(string $key, array $payload): void
    {
        $file = $this->path($key);
        $json = (string) \json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        // نوشتن اتمیک: tmp + rename
        $tmp = $file . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (@\file_put_contents($tmp, $json) !== false) {
            @\chmod($tmp, $this->permission);
            if (!@\rename($tmp, $file)) {
                @\unlink($tmp);
                @\file_put_contents($file, $json);
            }
        }
    }

    private function path(string $key): string
    {
        if (\strlen($key) > 512) {
            $key = \hash('xxh128', $key);
        }

        $hash = \hash('xxh128', $this->prefix . $key);
        $shard = $this->directory . '/' . \substr($hash, 0, 2) . '/' . \substr($hash, 2, 2);
        $this->ensureDirectory($shard);

        return $shard . '/' . $hash . '.cache';
    }

    private function ensureDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            @\mkdir($dir, self::DIR_MODE, true);
        }
    }

    private function clearDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && \str_ends_with($file->getFilename(), '.cache')) {
                @\unlink($file->getPathname());
            }
        }
    }
}
