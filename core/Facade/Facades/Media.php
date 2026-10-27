<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade رسانه (Media).
 *
 * @method static \Tavaan\Engine\Media\Media upload(object $file, ?string $disk = null, string $folder = '')
 * @method static \Tavaan\Engine\Media\Media fromPath(string $path, ?string $disk = null, string $folder = '', ?string $originalName = null)
 * @method static array variants(\Tavaan\Engine\Media\Media $media, array $definitions)
 * @method static \Tavaan\Engine\Media\Media|null find(string $id)
 * @method static \Tavaan\Engine\Media\Media|null findByHash(string $hash)
 * @method static array{rows: list<\Tavaan\Engine\Media\Media>, total: int} paginate(string $search = '', string $mimePrefix = '', int $page = 1, int $perPage = 24)
 * @method static \Tavaan\Engine\Media\Media attach(\Tavaan\Engine\Media\Media $media, string $type, int|string $id)
 * @method static bool delete(\Tavaan\Engine\Media\Media|string $media)
 * @mixin \Tavaan\Engine\Media\MediaManager
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class Media extends Facade
{
    protected static function accessor(): string
    {
        return 'media';
    }
}
