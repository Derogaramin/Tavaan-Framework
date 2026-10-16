<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Encryption\EncrypterInterface;
use Tavaan\Core\Contracts\Filesystem\FilesystemInterface;
use Tavaan\Engine\Filesystem\Adapters\FtpFilesystem;
use Tavaan\Engine\Filesystem\Adapters\LocalFilesystem;
use Tavaan\Engine\Filesystem\Adapters\MemoryFilesystem;
use Tavaan\Engine\Filesystem\Adapters\S3Filesystem;
use Tavaan\Engine\Filesystem\Exceptions\FilesystemException;

/**
 * تَوان | Tavaan — Filesystem
 * FilesystemManager — مدیر diskهای چندگانه (local/s3/ftp/memory؛ DOC-063 §4).
 *
 * متدهای FilesystemInterface به disk پیش‌فرض واگذار می‌شوند (Facade: Storage).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class FilesystemManager implements FilesystemInterface
{
    /** @var array<string, FilesystemInterface> */
    private array $disks = [];

    /** @var array<string, \Closure(array<string, mixed>, ?ContainerInterface): FilesystemInterface> */
    private array $extensions = [];

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        private readonly ?EncrypterInterface $encrypter = null,
    ) {
    }

    // ─── diskها ───────────────────────────────────────────────────────────

    public function getDefaultDisk(): string
    {
        return (string) ($this->config?->get('filesystem.default', 'local') ?? 'local');
    }

    public function disk(?string $name = null): FilesystemInterface
    {
        $name ??= $this->getDefaultDisk();

        return $this->disks[$name] ??= $this->resolveDisk($name);
    }

    /** ثبت درایور سفارشی. */
    public function extend(string $driver, \Closure $factory): void
    {
        $this->extensions[$driver] = $factory;
    }

    /** disk پیش‌فرض (برای Facade). */
    public function diskName(): string
    {
        return $this->getDefaultDisk();
    }

    private function resolveDisk(string $name): FilesystemInterface
    {
        $config = (array) ($this->config?->get('filesystem.disks.' . $name, []) ?? []);
        if ($config === []) {
            throw FilesystemException::diskNotConfigured($name);
        }

        $driver = (string) ($config['driver'] ?? $name);

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($config, $this->container);
        }

        return match ($driver) {
            'local' => new LocalFilesystem($name, $config, $this->localSigner()),
            'memory' => new MemoryFilesystem($name, (string) ($config['url'] ?? '')),
            's3' => new S3Filesystem($name, $config),
            'ftp', 'ftps' => new FtpFilesystem($name, $config),
            default => throw FilesystemException::unknownDriver($driver),
        };
    }

    /** امضاکنندهٔ temporaryUrl برای diskهای local (HMAC با کلید اپ). */
    private function localSigner(): ?\Closure
    {
        $encrypter = $this->encrypter;

        return $encrypter === null
            ? null
            : static fn (string $path, string $expires): string => $encrypter->hmac($path . '|' . $expires, 'filesystem.tempurl');
    }

    // ─── putFile / download ───────────────────────────────────────────────

    /**
     * ذخیرهٔ فایل آپلودشده با نام تصادفی (DOC-063 §3 / DOC-074 §2: rename امن).
     *
     * @param object $file UploadedFile (http engine) یا هر شیء با move()/path()
     *
     * @return string|false مسیر ذخیره‌شده (false در صورت شکست)
     */
    public function putFile(string $directory, object $file, string $visibility = '', ?string $disk = null): string|false
    {
        $extension = \method_exists($file, 'extension') ? (string) $file->extension() : '';
        $name = \bin2hex(\random_bytes(20)) . ($extension !== '' ? '.' . $extension : '');

        return $this->putFileAs($directory, $file, $name, $visibility, $disk);
    }

    /** @param object $file */
    public function putFileAs(string $directory, object $file, string $name, string $visibility = '', ?string $disk = null): string|false
    {
        $path = \trim($directory, '/') === '' ? $name : \trim($directory, '/') . '/' . $name;
        $target = $this->disk($disk);

        if (\method_exists($file, 'path')) {
            $content = @\file_get_contents((string) $file->path());
            if ($content !== false) {
                $ok = $target->put($path, $content);
                if ($ok && $visibility !== '') {
                    $target->setVisibility($path, $visibility);
                }

                return $ok ? $path : false;
            }
        }

        if (\method_exists($file, 'move')) {
            $tmpDir = \sys_get_temp_dir() . '/tvn-upload-' . \bin2hex(\random_bytes(4));
            \mkdir($tmpDir, 0700, true);
            $moved = (string) $file->move($tmpDir, $name);
            $movedPath = $moved !== '' ? $moved : $tmpDir . '/' . $name;
            $content = @\file_get_contents($movedPath);
            @\unlink($movedPath);
            @\rmdir($tmpDir);

            if ($content === false) {
                return false;
            }

            return $target->put($path, $content) ? $path : false;
        }

        return false;
    }

    /**
     * پاسخ دانلود (Content-Disposition: attachment) — وابستگی نرم به موتور http.
     *
     * @return object Response
     */
    public function download(string $path, ?string $name = null, ?string $disk = null): object
    {
        $filesystem = $disk === null ? $this->disk() : $this->disk($disk);
        $content = $filesystem->get($path);
        if ($content === null) {
            throw new \Tavaan\Core\Exceptions\Filesystem\FileNotFoundException('filesystem.file_not_found', ['path' => $path]);
        }

        $name ??= \basename($path);
        $mime = $filesystem->mimeType($path);

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) \strlen($content),
            'Content-Disposition' => 'attachment; filename="' . \addcslashes($name, '"') . '"',
            'Cache-Control' => 'no-store',
        ];

        $responseClass = \Tavaan\Engine\Http\Response::class;
        if (!\class_exists($responseClass)) {
            throw FilesystemException::notSupported('download (بدون موتور http)', $filesystem->diskName());
        }

        /** @var object */
        return new $responseClass($content, 200, $headers);
    }

    // ─── symlink عمومی (storage:link) ─────────────────────────────────────

    /**
     * ساخت symlinkهای config (filesystem.links) — DOC-063 §4.
     *
     * @return list<string> مسیرهای ساخته‌شده
     */
    public function createPublicLinks(): array
    {
        $links = (array) ($this->config?->get('filesystem.links', []) ?? []);
        $created = [];

        foreach ($links as $link => $target) {
            $link = (string) $link;
            $target = (string) $target;

            if (\is_link($link)) {
                continue;
            }
            if (!\is_dir($target)) {
                \mkdir($target, 0775, true);
            }
            $dir = \dirname($link);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0775, true);
            }
            if (@\symlink($target, $link)) {
                $created[] = $link;
            }
        }

        return $created;
    }

    // ─── FilesystemInterface → disk پیش‌فرض ───────────────────────────────

    public function get(string $path): ?string
    {
        return $this->disk()->get($path);
    }

    /** @return resource|null */
    public function readStream(string $path)
    {
        return $this->disk()->readStream($path);
    }

    public function put(string $path, string $content): bool
    {
        return $this->disk()->put($path, $content);
    }

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool
    {
        return $this->disk()->writeStream($path, $stream);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function missing(string $path): bool
    {
        return $this->disk()->missing($path);
    }

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool
    {
        return $this->disk()->delete($paths);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->disk()->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->disk()->move($from, $to);
    }

    public function size(string $path): int
    {
        return $this->disk()->size($path);
    }

    public function lastModified(string $path): int
    {
        return $this->disk()->lastModified($path);
    }

    public function mimeType(string $path): string
    {
        return $this->disk()->mimeType($path);
    }

    public function append(string $path, string $content): bool
    {
        return $this->disk()->append($path, $content);
    }

    public function prepend(string $path, string $content): bool
    {
        return $this->disk()->prepend($path, $content);
    }

    /** @return list<string> */
    public function files(string $directory = ''): array
    {
        return $this->disk()->files($directory);
    }

    /** @return list<string> */
    public function allFiles(string $directory = ''): array
    {
        return $this->disk()->allFiles($directory);
    }

    /** @return list<string> */
    public function directories(string $directory = ''): array
    {
        return $this->disk()->directories($directory);
    }

    /** @return list<string> */
    public function allDirectories(string $directory = ''): array
    {
        return $this->disk()->allDirectories($directory);
    }

    public function makeDirectory(string $directory): bool
    {
        return $this->disk()->makeDirectory($directory);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->disk()->deleteDirectory($directory);
    }

    public function visibility(string $path): string
    {
        return $this->disk()->visibility($path);
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        return $this->disk()->setVisibility($path, $visibility);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        return $this->disk()->temporaryUrl($path, $expiration);
    }
}
