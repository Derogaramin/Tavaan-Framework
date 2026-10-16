<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

/**
 * تَوان | Tavaan — Filesystem
 * MemoryFilesystem — درایور درون‌حافظه‌ای (تست؛ DOC-063 §2).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class MemoryFilesystem extends AbstractFilesystem
{
    /** @var array<string, array{content: string, visibility: string, mtime: int}> */
    private array $files = [];

    /** @var array<string, true> */
    private array $directories = [];

    public function __construct(private readonly string $diskName = 'memory', private readonly string $urlBase_ = '')
    {
        $this->urlBase = $urlBase_;
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function get(string $path): ?string
    {
        return $this->files[$this->n($path)]['content'] ?? null;
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        $content = $this->get($path);
        if ($content === null) {
            return null;
        }

        $stream = \fopen('php://temp', 'w+b');
        if ($stream === false) {
            return null;
        }
        \fwrite($stream, $content);
        \rewind($stream);

        return $stream;
    }

    public function put(string $path, string $content): bool
    {
        $key = $this->n($path);
        $this->files[$key] = [
            'content' => $content,
            'visibility' => $this->files[$key]['visibility'] ?? 'private',
            'mtime' => \time(),
        ];
        $this->markParentDirectories($key);

        return true;
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool
    {
        if (!\is_resource($stream)) {
            return false;
        }

        $content = '';
        while (!\feof($stream)) {
            $chunk = \fread($stream, 8192);
            if ($chunk === false) {
                break;
            }
            $content .= $chunk;
        }

        return $this->put($path, $content);
    }

    public function exists(string $path): bool
    {
        $key = $this->n($path);

        return isset($this->files[$key]) || isset($this->directories[$key]);
    }

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool
    {
        foreach ((array) $paths as $path) {
            unset($this->files[$this->n($path)]);
        }

        return true;
    }

    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);
        if ($content === null) {
            $this->fileNotFound($from);
        }

        return $this->put($to, $content);
    }

    public function move(string $from, string $to): bool
    {
        $this->copy($from, $to);

        return $this->delete($from);
    }

    public function size(string $path): int
    {
        $content = $this->get($path);
        if ($content === null) {
            $this->fileNotFound($path);
        }

        return \strlen($content);
    }

    public function lastModified(string $path): int
    {
        $entry = $this->files[$this->n($path)] ?? null;
        if ($entry === null) {
            $this->fileNotFound($path);
        }

        return $entry['mtime'];
    }

    public function mimeType(string $path): string
    {
        if ($this->get($path) === null) {
            $this->fileNotFound($path);
        }

        return self::mimeFromExtension($path);
    }

    public function append(string $path, string $content): bool
    {
        return $this->put($path, ($this->get($path) ?? '') . $content);
    }

    public function prepend(string $path, string $content): bool
    {
        return $this->put($path, $content . ($this->get($path) ?? ''));
    }

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
        $this->directories[$this->n($directory)] = true;
        $this->markParentDirectories($this->n($directory));

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        $prefix = $this->n($directory) . '/';
        foreach (\array_keys($this->files) as $key) {
            if (\str_starts_with($key, $prefix)) {
                unset($this->files[$key]);
            }
        }
        foreach (\array_keys($this->directories) as $key) {
            if (\str_starts_with($key, $prefix) || $key === $this->n($directory)) {
                unset($this->directories[$key]);
            }
        }

        return true;
    }

    public function visibility(string $path): string
    {
        return $this->files[$this->n($path)]['visibility'] ?? 'private';
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        $key = $this->n($path);
        if (!isset($this->files[$key])) {
            return false;
        }
        $this->files[$key]['visibility'] = $visibility === 'public' ? 'public' : 'private';

        return true;
    }

    public function url(string $path): string
    {
        $normalized = $this->n($path);

        return $this->urlBase !== ''
            ? \rtrim($this->urlBase, '/') . '/' . $normalized
            : '/memory/' . $normalized;
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        return $this->url($path) . '?expires=' . $expiration->getTimestamp();
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function n(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = (string) (\preg_replace('#/+#', '/', $path) ?? $path);

        return \trim($path, '/');
    }

    private function markParentDirectories(string $key): void
    {
        $parts = \explode('/', $key);
        \array_pop($parts);
        $current = '';
        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current . '/' . $part;
            $this->directories[$current] = true;
        }
    }

    /** @return list<string> */
    private function scan(string $directory, bool $files, bool $recursive): array
    {
        $prefix = $this->n($directory);
        $prefixWithSlash = $prefix === '' ? '' : $prefix . '/';
        $out = [];

        if ($files) {
            foreach (\array_keys($this->files) as $key) {
                if ($prefixWithSlash !== '' && !\str_starts_with($key, $prefixWithSlash)) {
                    continue;
                }
                $relative = \substr($key, \strlen($prefixWithSlash));
                if ($relative === '' || (!$recursive && \str_contains($relative, '/'))) {
                    continue;
                }
                $out[] = $key;
            }
        } else {
            foreach (\array_keys($this->directories) as $key) {
                if ($prefixWithSlash !== '' && !\str_starts_with($key, $prefixWithSlash)) {
                    continue;
                }
                $relative = \substr($key, \strlen($prefixWithSlash));
                if ($relative === '' || (!$recursive && \str_contains($relative, '/'))) {
                    continue;
                }
                $out[] = $key;
            }
        }

        \sort($out);

        return $out;
    }
}
