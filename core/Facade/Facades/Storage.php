<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade سامانهٔ فایل (Storage).
 *
 * @method static \Tavaan\Core\Contracts\Filesystem\FilesystemInterface disk(?string $name = null)
 * @method static string|null get(string $path)
 * @method static resource|null readStream(string $path)
 * @method static bool put(string $path, string $content)
 * @method static bool writeStream(string $path, $stream)
 * @method static bool exists(string $path)
 * @method static bool missing(string $path)
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string mimeType(string $path)
 * @method static bool append(string $path, string $content)
 * @method static bool prepend(string $path, string $content)
 * @method static list<string> files(string $directory = '')
 * @method static list<string> allFiles(string $directory = '')
 * @method static list<string> directories(string $directory = '')
 * @method static list<string> allDirectories(string $directory = '')
 * @method static bool makeDirectory(string $directory)
 * @method static bool deleteDirectory(string $directory)
 * @method static string visibility(string $path)
 * @method static bool setVisibility(string $path, string $visibility)
 * @method static string url(string $path)
 * @method static string temporaryUrl(string $path, \DateTimeInterface $expiration)
 * @method static string|false putFile(string $directory, object $file, string $visibility = '')
 * @method static string|false putFileAs(string $directory, object $file, string $name, string $visibility = '')
 * @method static object download(string $path, ?string $name = null, ?string $disk = null)
 * @mixin \Tavaan\Engine\Filesystem\FilesystemManager
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
final class Storage extends Facade
{
    protected static function accessor(): string
    {
        return 'filesystem';
    }
}
