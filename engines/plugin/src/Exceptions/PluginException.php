<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Plugin
 * PluginException — خطاهای چرخهٔ عمر/اعتبارسنجی افزونه (DOC-079).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginException extends TavaanException
{
    public static function manifestInvalid(string $pluginDir, string $reason): self
    {
        return new self(
            messageId: 'plugin.manifest_invalid',
            context: ['dir' => $pluginDir, 'reason' => $reason],
            hint: 'plugin.json را با الگوی DOC-079 §3 مقایسه کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function signatureMissing(string $id): self
    {
        return new self(
            messageId: 'plugin.signature_missing',
            context: ['plugin' => $id],
            hint: 'پلاگین را با کلید Ed25519 فروشنده امضا کنید یا (فقط dev) TVN_PLUGIN_SIGNATURE=false.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function signatureInvalid(string $id, string $reason = ''): self
    {
        return new self(
            messageId: 'plugin.signature_invalid',
            context: ['plugin' => $id, 'reason' => $reason],
            hint: 'امضا با کلید عمومی فروشنده در trusted_vendors تطبیق نکرد — فایل دستکاری شده یا کلید اشتباه است.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function vendorUntrusted(string $author): self
    {
        return new self(
            messageId: 'plugin.vendor_untrusted',
            context: ['author' => $author],
            hint: 'کلید عمومی فروشنده را در config/plugins.php → trusted_vendors اضافه کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function lockTampered(string $id, string $details): self
    {
        return new self(
            messageId: 'plugin.lock_tampered',
            context: ['plugin' => $id, 'details' => $details],
            hint: 'hash فایل‌ها با plugins.lock نمی‌خواند — پلاگین پس از نصب تغییر کرده است. reinstall کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function lockMissing(string $id): self
    {
        return new self(
            messageId: 'plugin.lock_missing',
            context: ['plugin' => $id],
            hint: 'پلاگین در plugins.lock ثبت نشده — ابتدا plugin:install اجرا شود.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function notFound(string $id): self
    {
        return new self(
            messageId: 'plugin.not_found',
            context: ['plugin' => $id],
            hint: 'با `tvn plugin:list` شناسه‌ها را بررسی کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function alreadyInstalled(string $id): self
    {
        return new self(
            messageId: 'plugin.already_installed',
            context: ['plugin' => $id],
            hint: 'برای نسخهٔ جدید از `tvn plugin:update` استفاده کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function capabilityDenied(string $id, string $capability, string $reason): self
    {
        return new self(
            messageId: 'plugin.capability_denied',
            context: ['plugin' => $id, 'capability' => $capability, 'reason' => $reason],
            hint: 'capability را از plugin.json حذف کنید یا سیاست sandbox را در config/plugins.php بازبینی کنید.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function hookNotDeclared(string $hook, string $id): self
    {
        return new self(
            messageId: 'plugin.hook_not_declared',
            context: ['hook' => $hook, 'plugin' => $id],
            hint: 'میزبان باید hook را در module.json ("hooks") یا config/plugins.php → allowed_hooks اعلام کند.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    /** @param list<string> $findings */
    public static function dangerousCode(string $id, array $findings): self
    {
        return new self(
            messageId: 'plugin.dangerous_code',
            context: ['plugin' => $id, 'findings' => \array_slice($findings, 0, 20)],
            hint: 'یافته‌های scan را بررسی کنید؛ فقط با تأیید صریح admin از --force استفاده شود.',
            docsUrl: '03-engines/plugin.html',
        );
    }

    public static function disabled(): self
    {
        return new self(
            messageId: 'plugin.disabled',
            context: [],
            hint: 'TVN_PLUGINS=false است — سیستم افزونه غیرفعال است.',
            docsUrl: '03-engines/plugin.html',
        );
    }
}
