<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * پایهٔ خطاهای ماژول (حوزهٔ کد: MD).
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
class ModuleException extends TavaanException
{
    public static function manifestUnreadable(string $path): self
    {
        return new self('module.manifest_unreadable', ['path' => $path], 'فایل module.json خواندنی نیست.');
    }

    public static function manifestInvalidJson(string $path, string $error): self
    {
        return new self('module.manifest_invalid_json', ['path' => $path, 'error' => $error], 'JSON نامعتبر در manifest.');
    }

    public static function manifestMissingField(string $path, string $field): self
    {
        return new self('module.manifest_missing_field', ['path' => $path, 'field' => $field], 'فیلد الزامی manifest موجود نیست.');
    }

    public static function invalidId(string $id): self
    {
        return new self('module.invalid_id', ['id' => $id], 'شناسهٔ ماژول باید `<vendor>.<name>` (kebab) باشد.');
    }

    public static function invalidVersion(string $version): self
    {
        return new self('module.invalid_version', ['version' => $version], 'نسخه باید SemVer معتبر باشد.');
    }

    public static function hasDependents(string $id, array $dependents): self
    {
        return new self('module.has_dependents', ['module' => $id, 'dependents' => $dependents], 'ابتدا ماژول‌های وابسته را غیرفعال کنید.');
    }

    public static function notFound(string $id): self
    {
        return new self('module.not_found', ['module' => $id], 'ماژول کشف نشد؛ module.json و مسیرها را بررسی کنید.');
    }
}
