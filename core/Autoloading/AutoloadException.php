<?php

declare(strict_types=1);

namespace Tavaan\Core\Autoloading;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Core
 * AutoloadException — خطای بارگذاری خودکار کلاس (نام ناامن، فرار از basePath، کلاس declare نشده).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class AutoloadException extends TavaanException
{
    /**
     * نام کلاس ناامن (کاراکتر غیرمجاز/طولانی) — احتمال Path Traversal.
     */
    public static function unsafeClassName(string $class): self
    {
        return new self(
            messageId: 'core.autoload.unsafe_class_name',
            context: ['class' => \substr($class, 0, 64)],
            hint: 'نام کلاس فقط می‌تواند شامل [A-Za-z0-9_\\] باشد.',
            docsUrl: 'docs/02-architecture/bootstrapping.md#24-امنیت-autoloader',
        );
    }

    /**
     * فایل خارج از basePath پروژه قرار دارد.
     */
    public static function pathEscape(string $file): self
    {
        return new self(
            messageId: 'core.autoload.path_escape',
            context: ['file' => $file],
            hint: 'نگاشت namespace نباید به فایلی خارج از ریشهٔ پروژه اشاره کند.',
        );
    }

    /**
     * فایل require شد اما کلاس مورد انتظار declare نشده است.
     */
    public static function classNotDeclared(string $class, string $file): self
    {
        return new self(
            messageId: 'core.autoload.class_not_declared',
            context: ['class' => $class, 'file' => $file],
            hint: 'نام کلاس درون فایل با نام فایل/namespace مطابقت ندارد.',
        );
    }
}
