<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * FtpFilesystem — درایور FTP/FTPS با stream wrapper داخلی PHP (DOC-063 §2).
 *
 * 🔒 بدون ext-ftp — از `ftp://`/`ftps://` streamها استفاده می‌کند.
 * Credential فقط از config (ENV) — در URL ساخته می‌شود و لاگ نمی‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class FtpFilesystem extends AbstractFilesystem
{
    private readonly string $scheme;

    private readonly string $authority;

    private readonly string $root;

    private readonly bool $passive;

    private readonly float $timeout;

    /** @var resource|null */
    private $context = null;

    /**
     * @param array<string, mixed> $config {host, port, username, password, ssl, root, passive, timeout}
     */
    public function __construct(
        private readonly string $diskName,
        #[\SensitiveParameter] private readonly array $config = [],
    ) {
        $this->scheme = (bool) ($config['ssl'] ?? false) ? 'ftps' : 'ftp';
        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 21);
        $user = \rawurlencode((string) ($config['username'] ?? 'anonymous'));
        $pass = \rawurlencode((string) ($config['password'] ?? ''));
        $this->authority = $user . ':' . $pass . '@' . $host . ':' . $port;
        $this->root = '/' . \trim((string) ($config['root'] ?? ''), '/');
        $this->passive = (bool) ($config['passive'] ?? true);
        $this->timeout = (float) ($config['timeout'] ?? 15.0);
    }

    public function diskName(): string
    {
        return $this->diskName;
    }

    public function get(string $path): ?string
    {
        if (!$this->exists($path)) {
            return null;
        }

        $content = @\file_get_contents($this->uri($path), false, $this->context());

        return $content === false ? null : $content;
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        if (!$this->exists($path)) {
            return null;
        }

        $stream = @\fopen($this->uri($path), 'rb', false, $this->context());

        return $stream === false ? null : $stream;
    }

    public function put(string $path, string $content): bool
    {
        $written = @\file_put_contents($this->uri($path), $content, 0, $this->context());
        if ($written === false) {
            throw FilesystemException::operationFailed('put', $path, 'ftp write failed');
        }

        return true;
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool
    {
        if (!\is_resource($stream)) {
            throw FilesystemException::operationFailed('writeStream', $path, 'resource نامعتبر');
        }

        $target = @\fopen($this->uri($path), 'wb', false, $this->context());
        if ($target === false) {
            throw FilesystemException::operationFailed('writeStream', $path, 'ftp open failed');
        }

        try {
            // 🔒 حافظهٔ ثابت: chunk-chunk
            \stream_copy_to_stream($stream, $target);
        } finally {
            \fclose($target);
        }

        return true;
    }

    public function exists(string $path): bool
    {
        // 🔒 file_exists بدون context است — برای FTP با اعتبارنامه، fopen+context معیار است
        $stream = @\fopen($this->uri($path), 'rb', false, $this->context());
        if ($stream === false) {
            // ممکن است پوشه باشد
            $dir = @\opendir($this->uri($path), $this->context());
            if ($dir === false) {
                return false;
            }
            \closedir($dir);

            return true;
        }
        \fclose($stream);

        return true;
    }

    public function size(string $path): int
    {
        $stream = @\fopen($this->uri($path), 'rb', false, $this->context());
        if ($stream === false) {
            $this->fileNotFound($path);
        }
        $stat = \fstat($stream);
        \fclose($stream);

        return (int) ($stat['size'] ?? 0);
    }

    public function lastModified(string $path): int
    {
        $stream = @\fopen($this->uri($path), 'rb', false, $this->context());
        if ($stream === false) {
            $this->fileNotFound($path);
        }
        $stat = \fstat($stream);
        \fclose($stream);

        return (int) ($stat['mtime'] ?? 0);
    }

    public function mimeType(string $path): string
    {
        if (!$this->exists($path)) {
            $this->fileNotFound($path);
        }

        // بدون fileinfo روی سرور راه دور — از extension
        return self::mimeFromExtension($path);
    }

    public function append(string $path, string $content): bool
    {
        $stream = @\fopen($this->uri($path), 'ab', false, $this->context());
        if ($stream === false) {
            return false;
        }
        \fwrite($stream, $content);
        \fclose($stream);

        return true;
    }

    public function prepend(string $path, string $content): bool
    {
        return $this->put($path, $content . ($this->get($path) ?? ''));
    }

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool
    {
        $ok = true;
        foreach ((array) $paths as $path) {
            if ($this->exists($path)) {
                $ok = $ok && @\unlink($this->uri($path), $this->context());
            }
        }

        return $ok;
    }

    public function copy(string $from, string $to): bool
    {
        return @\copy($this->uri($from), $this->uri($to), $this->context());
    }

    public function move(string $from, string $to): bool
    {
        return @\rename($this->uri($from), $this->uri($to), $this->context());
    }

    /** @return list<string> */
    public function files(string $directory = ''): array
    {
        return $this->scan($directory, files: true);
    }

    /** @return list<string> */
    public function allFiles(string $directory = ''): array
    {
        // FTP: recursive گران است — همان scan + زیرپوشه‌های مستقیم به‌صورت بازگشتی
        $out = $this->scan($directory, files: true);
        foreach ($this->scan($directory, files: false) as $sub) {
            foreach ($this->allFiles($sub) as $nested) {
                $out[] = $nested;
            }
        }
        \sort($out);

        return $out;
    }

    /** @return list<string> */
    public function directories(string $directory = ''): array
    {
        return $this->scan($directory, files: false);
    }

    /** @return list<string> */
    public function allDirectories(string $directory = ''): array
    {
        $out = $this->scan($directory, files: false);
        foreach ($out as $sub) {
            foreach ($this->allDirectories($sub) as $nested) {
                $out[] = $nested;
            }
        }
        \sort($out);

        return $out;
    }

    public function makeDirectory(string $directory): bool
    {
        return @\mkdir($this->uri($directory), 0775, true, $this->context());
    }

    public function deleteDirectory(string $directory): bool
    {
        foreach ($this->allFiles($directory) as $file) {
            @\unlink($this->uri($file), $this->context());
        }
        foreach (\array_reverse($this->allDirectories($directory)) as $sub) {
            @\rmdir($this->uri($sub), $this->context());
        }

        return @\rmdir($this->uri($directory), $this->context());
    }

    public function visibility(string $path): string
    {
        return 'private';    // FTP استاندارد ACL ندارد
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        // no-op محترمانه — برخی سرورها SITE CHMOD دارند؛ اینجا portable نمی‌شود
        return $this->exists($path);
    }

    public function url(string $path): string
    {
        $host = (string) ($this->config['host'] ?? 'localhost');

        return 'ftp://' . $host . $this->full($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        throw FilesystemException::notSupported('temporaryUrl', 'ftp');
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function uri(string $path): string
    {
        return $this->scheme . '://' . $this->authority . $this->full($path);
    }

    private function full(string $path): string
    {
        $normalized = \trim(\str_replace('\\', '/', $path), '/');

        return \rtrim($this->root, '/') . '/' . $normalized;
    }

    /** @return resource */
    private function context()
    {
        if ($this->context !== null) {
            return $this->context;
        }

        $context = \stream_context_create([
            'ftp' => ['passive' => $this->passive, 'overwrite' => true, 'timeout' => (int) $this->timeout],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        if ($context === false) {
            throw FilesystemException::transportError('ftp context');
        }

        return $this->context = $context;
    }

    /** @return list<string> */
    private function scan(string $directory, bool $files): array
    {
        $dir = @\opendir($this->uri($directory), $this->context());
        if ($dir === false) {
            return [];
        }

        $prefix = \trim(\str_replace('\\', '/', $directory), '/');
        $out = [];
        while (($item = \readdir($dir)) !== false) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $relative = $prefix === '' ? $item : $prefix . '/' . $item;
            $isDir = @\is_dir($this->uri($relative));
            if ($isDir !== $files) {
                $out[] = $relative;
            }
        }
        \closedir($dir);
        \sort($out);

        return $out;
    }
}
