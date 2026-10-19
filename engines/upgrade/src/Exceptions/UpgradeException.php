<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Upgrade
 * UpgradeException — خطاهای جریان ارتقا (DOC-081E).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeException extends TavaanException
{
    public static function noPath(string $from, string $to): self
    {
        return new self(
            messageId: 'upgrade.no_path',
            context: ['from' => $from, 'to' => $to],
            hint: 'نسخهٔ هدف باید در upgrades/ وجود داشته باشد و بزرگ‌تر از نسخهٔ جاری باشد (ارتقا گام‌به‌گام است).',
            docsUrl: '03-engines/upgrade.html',
        );
    }

    /** @param list<string> $reasons */
    public static function precheckFailed(array $reasons): self
    {
        return new self(
            messageId: 'upgrade.precheck_failed',
            context: ['reasons' => $reasons],
            hint: 'پیش‌نیازهای §2 گام ۱ را برطرف کنید (PHP/افزونه‌ها/فضای دیسک).',
            docsUrl: '03-engines/upgrade.html',
        );
    }

    public static function patchFailed(string $version, string $patch, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            messageId: 'upgrade.patch_failed',
            context: ['version' => $version, 'patch' => $patch, 'reason' => $reason],
            hint: 'patch باید idempotent باشد؛ پس از رفع خطا، `tvn upgrade:rollback` یا اجرای مجدد (patchهای موفق skip می‌شوند).',
            docsUrl: '03-engines/upgrade.html',
            previous: $previous,
        );
    }

    public static function noBackup(): self
    {
        return new self(
            messageId: 'upgrade.no_backup',
            context: [],
            hint: 'هیچ backup ثبت‌شده‌ای برای بازگشت وجود ندارد.',
            docsUrl: '03-engines/upgrade.html',
        );
    }

    public static function backupFailed(string $reason): self
    {
        return new self(
            messageId: 'upgrade.backup_failed',
            context: ['reason' => $reason],
            hint: 'ارتقا بدون backup مجاز نیست (§8) — مگر --no-backup در dev.',
            docsUrl: '03-engines/upgrade.html',
        );
    }

    public static function verifyFailed(string $reason): self
    {
        return new self(
            messageId: 'upgrade.verify_failed',
            context: ['reason' => $reason],
            hint: 'health check پس از ارتقا شکست خورد — rollback خودکار انجام می‌شود.',
            docsUrl: '03-engines/upgrade.html',
        );
    }

    public static function invalidVersion(string $version): self
    {
        return new self(
            messageId: 'upgrade.invalid_version',
            context: ['version' => $version],
            hint: 'نسخه باید semver معتبر باشد (X.Y.Z).',
            docsUrl: '03-engines/upgrade.html',
        );
    }
}
