<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Module
 * KitException — خطاهای نصب/کشف کیت (Fail Explainable — DOC-013).
 */

namespace Tavaan\Engine\Module\Kit;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * @api 1.0
 * @see docs/05-applications/README.md
 */
final class KitException extends TavaanException
{
    public static function notFound(string $kit): self
    {
        return new self(
            messageId: 'kit.not_found',
            context: ['kit' => $kit],
            hint: 'کیت را در kits/ بررسی کنید یا `tvn kit:list` بزنید.',
        );
    }

    public static function invalidManifest(string $path, string $reason): self
    {
        return new self(
            messageId: 'kit.invalid_manifest',
            context: ['path' => $path, 'reason' => $reason],
            hint: 'kit.json را با الگوی DOC-090 §2.1 مقایسه کنید.',
        );
    }

    /** @param list<string> $problems */
    public static function requirementsNotMet(string $kit, array $problems): self
    {
        return new self(
            messageId: 'kit.requirements_not_met',
            context: ['kit' => $kit, 'problems' => $problems],
            hint: 'پیش از نصب، پیش‌نیازها را برطرف کنید.',
        );
    }

    public static function presetNotFound(string $kit, string $preset): self
    {
        return new self(
            messageId: 'kit.preset_not_found',
            context: ['kit' => $kit, 'preset' => $preset],
            hint: 'فهرست presetها در kits/<kit>/presets/ را ببینید.',
        );
    }

    /** @param list<string> $missing */
    public static function modulesMissing(string $kit, array $missing): self
    {
        return new self(
            messageId: 'kit.modules_missing',
            context: ['kit' => $kit, 'missing' => $missing],
            hint: 'ماژول‌های اعلام‌شده در kit.json باید در modules/ وجود داشته باشند.',
        );
    }
}
