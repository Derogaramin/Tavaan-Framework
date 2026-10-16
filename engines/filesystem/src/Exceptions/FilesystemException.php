<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Filesystem
 * FilesystemException — خطاهای موتور سامانهٔ فایل (DOC-063).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
class FilesystemException extends TavaanException
{
    public static function diskNotConfigured(string $disk): static
    {
        return new static('filesystem.disk_not_configured', ['disk' => $disk], 'disk را در config/filesystem.php تعریف کنید.');
    }

    public static function unknownDriver(string $driver): static
    {
        return new static('filesystem.unknown_driver', ['driver' => $driver]);
    }

    public static function operationFailed(string $operation, string $path, string $reason = ''): static
    {
        return new static('filesystem.operation_failed', ['op' => $operation, 'path' => $path, 'reason' => $reason]);
    }

    public static function signingUnavailable(): static
    {
        return new static('filesystem.signing_unavailable', [], 'برای temporaryUrl موتور crypt (APP_KEY) لازم است.');
    }

    public static function s3Error(int $status, string $body, string $operation = ''): static
    {
        return new static('filesystem.s3_error', ['status' => $status, 'op' => $operation, 'body' => \mb_substr($body, 0, 300)]);
    }

    public static function transportError(string $reason): static
    {
        return new static('filesystem.transport_error', ['reason' => $reason]);
    }

    public static function notSupported(string $operation, string $driver): static
    {
        return new static('filesystem.not_supported', ['op' => $operation, 'driver' => $driver]);
    }
}
