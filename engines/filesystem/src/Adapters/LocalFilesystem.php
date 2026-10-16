<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

use Tavaan\Core\Exceptions\Filesystem\PathTraversalException;
use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * LocalFilesystem — درایور local با **Path Sandbox** و **Atomic Write** (DOC-063 §5).
 *
 * 🔒 امنیت:
 * - Path Traversal: رد `..`، مسیر مطلق و null byte
 * - Symlink Escape: پس از realpath، مسیر باید داخل root بماند
 * - Atomic Write: tmp + rename (بدون partial write)
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class LocalFilesystem extends AbstractFilesystem
{
    private readonly string $root;

    private readonly string $defaultVisibility;

    /** @var \Closure(string, string): string|null */
    private $signer;

    /**
     * @param array<string, mixed> $config {root, url, visibility, permission, throw}
     */
    public function __construct(
        private readonly string $diskName,
        array $config = [],
        ?\Closure $signer = null,
    ) {
        $root = (string) ($config['root'] ?? \sys_get_temp_dir() . '/tavaan-fs');
        $this->root = \rtrim($root, '/\\');
        $this->defaultVisibility = (string) ($config['visibility'] ?? 'private');
        $this->urlBase = (string) ($config['url'] ?? '');
        $this->signer = $signer;

        if (!\is_dir($this->root)) {
            \mkdir($this->root, 0775, true);
        }
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function root(): string
    {
        return $this->root;
    }

    // ─── خواندن ───────────────────────────────────────────────────────────

    public function get(string $path): ?string
    {
        $full = $this->path($path);
        if (!\is_file($full)) {
            return null;
        }

        $content = \file_get_contents($full);

        return $content === false ? null : $content;
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        $full = $this->path($path);
        if (!\is_file($full)) {
            return null;
        }

        $stream = @\fopen($full, 'rb');

        return $stream === false ? null : $stream;
    }

    public function exists(string $path): bool
    {
        return \file_exists($this->path($path));
    }

    public function size(string $path): int
    {
        $full = $this->path($path);
        if (!\is_file($full)) {
            throw $this->fileNotFound($path);
        }

        return (int) \filesize($full);
    }

    public function lastModified(string $path): int
    {
        $full = $this->path($path);
        if (!\is_file($full)) {
            throw $this->fileNotFound($path);
        }

        return (int) \filemtime($full);
    }

    public function mimeType(string $path): string
    {
        $full = $this->path($path);
        if (!\is_file($full)) {
            throw $this->fileNotFound($path);
        }

        return $this->detectMime($full, $path);
    }

    // ─── نوشتن ────────────────────────────────────────────────────────────

    public function put(string $path, string $content): bool
    {
        $full = $this->path($path);
        $dir = \dirname($full);
        if (!\is_dir($dir) && (!\mkdir($dir, 0775, true) && !\is_dir($dir))) {
            throw FilesystemException::operationFailed('put', $path, 'mkdir failed');
        }

        // 🔒 Atomic Write: tmp + rename (DOC-063 §5.1)
        $tmp = $full . '.' . \bin2hex(\random_bytes(6)) . '.tmp';
        if (\file_put_contents($tmp, $content, \LOCK_EX) === false) {
            throw FilesystemException::operationFailed('put', $path, 'write failed');
        }
        @\chmod($tmp, $this->filePermission($this->visibilityFor($path)));

        if (!\rename($tmp, $full)) {
            @\unlink($tmp);

            throw FilesystemException::operationFailed('put', $path, 'rename failed');
        }

        return true;
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool
    {
        if (!\is_resource($stream)) {
            throw FilesystemException::operationFailed('writeStream', $path, 'resource نامعتبر');
        }

        // 🔒 حافظهٔ ثابت: chunk-chunk به tmp نوشته و rename می‌شود
        $full = $this->path($path);
        $dir = \dirname($full);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        $tmp = $full . '.' . \bin2hex(\random_bytes(6)) . '.tmp';
        $out = @\fopen($tmp, 'wb');
        if ($out === false) {
            throw FilesystemException::operationFailed('writeStream', $path, 'open tmp failed');
        }

        try {
            while (!\feof($stream)) {
                $chunk = \fread($stream, 8192);
                if ($chunk === false) {
                    break;
                }
                \fwrite($out, $chunk);
            }
        } finally {
            \fclose($out);
        }

        @\chmod($tmp, $this->filePermission($this->visibilityFor($path)));

        if (!\rename($tmp, $full)) {
            @\unlink($tmp);

            throw FilesystemException::operationFailed('writeStream', $path, 'rename failed');
        }

        return true;
    }

    public function append(string $path, string $content): bool
    {
        $full = $this->path($path);
        $dir = \dirname($full);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        return \file_put_contents($full, $content, \FILE_APPEND | \LOCK_EX) !== false;
    }

    public function prepend(string $path, string $content): bool
    {
        $existing = $this->get($path) ?? '';

        return $this->put($path, $content . $existing);
    }

    // ─── جابه‌جایی ────────────────────────────────────────────────────────

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool
    {
        $ok = true;
        foreach ((array) $paths as $path) {
            $full = $this->path($path);
            if (\is_file($full)) {
                $ok = $ok && @\unlink($full);
            }
        }

        return $ok;
    }

    public function copy(string $from, string $to): bool
    {
        $source = $this->path($from);
        if (!\is_file($source)) {
            throw $this->fileNotFound($from);
        }

        $dest = $this->path($to);
        $dir = \dirname($dest);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        return \copy($source, $dest);
    }

    public function move(string $from, string $to): bool
    {
        $source = $this->path($from);
        if (!\is_file($source)) {
            throw $this->fileNotFound($from);
        }

        $dest = $this->path($to);
        $dir = \dirname($dest);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        return \rename($source, $dest);
    }

    // ─── پوشه‌ها ──────────────────────────────────────────────────────────

    /** @return list<string> */
    public function files(string $directory = ''): array
    {
        return $this->scan($directory, files: true, recursive: false);
    }

    /** @return list<string> */
    public function allFiles(string $directory = ''): array
    {
        return $this->scan($directory, files: true, recursive: true);
    }

    /** @return list<string> */
    public function directories(string $directory = ''): array
    {
        return $this->scan($directory, files: false, recursive: false);
    }

    /** @return list<string> */
    public function allDirectories(string $directory = ''): array
    {
        return $this->scan($directory, files: false, recursive: true);
    }

    public function makeDirectory(string $directory): bool
    {
        $full = $this->path($directory);
        if (\is_dir($full)) {
            return true;
        }

        return \mkdir($full, 0775, true);
    }

    public function deleteDirectory(string $directory): bool
    {
        $full = $this->path($directory);
        if (!\is_dir($full)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @\rmdir($item->getPathname()) : @\unlink($item->getPathname());
        }

        return @\rmdir($full);
    }

    // ─── visibility ───────────────────────────────────────────────────────

    public function visibility(string $path): string
    {
        $full = $this->path($path);
        if (!\file_exists($full)) {
            return $this->defaultVisibility;
        }

        \clearstatcache(true, $full);
        $perms = (int) (@\fileperms($full) & 0o044);

        return ($perms & 0o004) !== 0 ? 'public' : 'private';
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        $full = $this->path($path);
        if (!\file_exists($full)) {
            return false;
        }

        $mode = \is_dir($full)
            ? ($visibility === 'public' ? 0775 : 0700)
            : $this->filePermission($visibility);

        $ok = @\chmod($full, $mode);
        \clearstatcache(true, $full);   // 🔒 fileperms از کش stat نخواند

        return $ok;
    }

    // ─── URL ──────────────────────────────────────────────────────────────

    public function url(string $path): string
    {
        $normalized = $this->normalize($path);

        if ($this->urlBase !== '') {
            return \rtrim($this->urlBase, '/') . '/' . $normalized;
        }

        return '/storage/' . $this->diskName . '/' . $normalized;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        if ($this->signer === null) {
            throw FilesystemException::signingUnavailable();
        }

        $expires = $expiration->getTimestamp();
        $normalized = $this->normalize($path);
        $signature = ($this->signer)($normalized, (string) $expires);

        return $this->url($path) . '?' . \http_build_query(['expires' => $expires, 'signature' => $signature]);
    }

    // ─── 🔒 Sandbox ───────────────────────────────────────────────────────

    /**
     * مسیر کامل ایمن — DOC-063 §5.
     *
     * @throws PathTraversalException
     */
    public function path(string $relative): string
    {
        $normalized = $this->normalize($relative);

        if (\str_contains($normalized, '..') || \str_starts_with($normalized, '/') || \str_contains($normalized, "\0")) {
            throw new PathTraversalException('filesystem.path_traversal', ['path' => $relative]);
        }

        $full = $this->root . '/' . $normalized;

        // 🔒 پس از resolve symlink، باید داخل root بماند
        $realRoot = \realpath($this->root);
        $real = \realpath($full);
        if ($real !== false && $realRoot !== false && !\str_starts_with($real, $realRoot . \DIRECTORY_SEPARATOR) && $real !== $realRoot) {
            throw new PathTraversalException('filesystem.path_traversal', ['path' => $relative]);
        }

        // والد موجود نیز بررسی شود (برای مسیرهای هنوز ساخته‌نشده)
        $parent = \realpath(\dirname($full));
        if ($parent !== false && $realRoot !== false
            && !\str_starts_with($parent, $realRoot . \DIRECTORY_SEPARATOR) && $parent !== $realRoot) {
            throw new PathTraversalException('filesystem.path_traversal', ['path' => $relative]);
        }

        return $full;
    }

    private function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = (string) (\preg_replace('#/+#', '/', $path) ?? $path);

        // segment-level رد `..` (حتی `a/../b`)
        $segments = [];
        foreach (\explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                // 🔒 هرگونه تلاش بالارفتن → همان‌جا رد می‌شود
                return '..' . $path;
            }
            $segments[] = $segment;
        }

        return \implode('/', $segments);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function scan(string $directory, bool $files, bool $recursive): array
    {
        $base = $directory === '' ? $this->root : $this->path($directory);
        if (!\is_dir($base)) {
            return [];
        }

        $prefix = $base === $this->root ? '' : $this->normalize($directory) . '/';
        $out = [];

        if (!$recursive) {
            foreach (\scandir($base) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $isFile = \is_file($base . '/' . $item);
                if ($isFile === $files) {
                    $out[] = $prefix . $item;
                }
            }
            \sort($out);

            return $out;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            // 🔒 برای پوشه‌ها SELF_FIRST — پوشه‌های غیرخالی هم برگردانده شوند
            $files ? \RecursiveIteratorIterator::LEAVES_ONLY : \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile() === $files) {
                $relative = \substr($item->getPathname(), \strlen($this->root) + 1);
                $out[] = \str_replace('\\', '/', $relative);
            } elseif (!$files && $item->isDir()) {
                $relative = \substr($item->getPathname(), \strlen($this->root) + 1);
                $out[] = \str_replace('\\', '/', $relative);
            }
        }
        \sort($out);

        return $out;
    }

    private function filePermission(string $visibility): int
    {
        return $visibility === 'public' ? 0664 : 0640;
    }

    private function visibilityFor(string $path): string
    {
        return $this->defaultVisibility;
    }
}
