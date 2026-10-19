<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

/**
 * تَوان | Tavaan — Plugin
 * PluginsLock — فایل `plugins.lock` با hash SHA-256 تک‌تک فایل‌های هر افزونه
 * (تشخیص دستکاری — DOC-079 §4). نوشتن اتمیک (tmp + rename + LOCK_EX).
 *
 * ساختار: `{ "<id>": { version, enabled, installed_at, updated_at, files: {rel: sha256} } }`
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginsLock
{
    public function __construct(private readonly string $file)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function read(): array
    {
        if (!\is_file($this->file)) {
            return [];
        }
        $raw = \file_get_contents($this->file);
        $decoded = \json_decode((string) $raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    public function has(string $id): bool
    {
        return isset($this->read()[$id]);
    }

    /** @return array<string, mixed>|null */
    public function entry(string $id): ?array
    {
        return $this->read()[$id] ?? null;
    }

    /** @param array<string, mixed> $entry */
    public function put(string $id, array $entry): void
    {
        $all = $this->read();
        $all[$id] = $entry;
        $this->write($all);
    }

    public function remove(string $id): void
    {
        $all = $this->read();
        unset($all[$id]);
        $this->write($all);
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $all = $this->read();
        if (!isset($all[$id])) {
            return;
        }
        $all[$id]['enabled'] = $enabled;
        $all[$id]['updated_at'] = \date('c');
        $this->write($all);
    }

    /**
     * hash همهٔ فایل‌های دایرکتوری افزونه (نسبی از ریشهٔ افزونه).
     *
     * @return array<string, string>
     */
    public static function hashDirectory(string $dir): array
    {
        $dir = \rtrim($dir, '/\\');
        if (!\is_dir($dir)) {
            return [];
        }

        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $full = \str_replace('\\', '/', $file->getPathname());
            if (\str_ends_with($full, '.bak')) {
                continue;   // پشتیبان‌های به‌روزرسانی داخل lock نمی‌روند
            }
            $relative = \substr($full, \strlen(\str_replace('\\', '/', $dir)) + 1);
            $hash = \hash_file('sha256', $full);
            if (\is_string($hash)) {
                $hashes[$relative] = $hash;
            }
        }
        \ksort($hashes);   // 🔒 ترتیب قطعی

        return $hashes;
    }

    /** @param array<string, array<string, mixed>> $all */
    private function write(array $all): void
    {
        \ksort($all);
        $dir = \dirname($this->file);
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create lock directory [%s].', $dir));
        }

        $json = (string) \json_encode((object) $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $this->file . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (\file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new \RuntimeException(\sprintf('Cannot write lock file [%s].', $this->file));
        }
        if (!\rename($tmp, $this->file)) {
            @\unlink($tmp);
            throw new \RuntimeException(\sprintf('Cannot install lock file [%s].', $this->file));
        }
    }
}
