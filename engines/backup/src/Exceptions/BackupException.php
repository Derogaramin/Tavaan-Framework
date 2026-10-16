<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Backup
 * BackupException — خطاهای موتور پشتیبان‌گیری (DOC-068).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
class BackupException extends TavaanException
{
    public static function notFound(string $id): static
    {
        return new static('backup.not_found', ['id' => $id]);
    }

    public static function verifyFailed(string $id, string $reason): static
    {
        return new static('backup.verify_failed', ['id' => $id, 'reason' => $reason]);
    }

    public static function decryptFailed(string $id, string $reason = ''): static
    {
        return new static('backup.decrypt_failed', ['id' => $id, 'reason' => $reason]);
    }

    public static function keyMissing(): static
    {
        return new static('backup.key_missing', [], 'کلید رمزنگاری backup (TVN_BACKUP_KEY) تنظیم نشده است.');
    }

    public static function forceRequired(): static
    {
        return new static('backup.force_required', [], 'restore مخرب است — با --force تأیید کنید.');
    }

    public static function storeFailed(string $reason): static
    {
        return new static('backup.store_failed', ['reason' => $reason]);
    }

    public static function unsafePath(string $path): static
    {
        return new static('backup.unsafe_path', ['path' => $path]);
    }
}
