<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Exceptions;

use Tavaan\Core\Exceptions\Core\ContainerException;

/**
 * TCR001 — binding/کلاس یافت یا ساخته نشد.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class BindingResolutionException extends ContainerException
{
    public static function notFound(string $abstract): self
    {
        return new self(
            messageId: 'container.binding_not_found',
            context: ['abstract' => $abstract],
            hint: 'کلاس/Interface را با bind/singleton ثبت کنید یا نام آن را بررسی نمایید.',
            docsUrl: 'docs/03-engines/container.md',
        );
    }

    public static function classNotFound(string $class): self
    {
        return new self(
            messageId: 'container.class_not_found',
            context: ['class' => $class],
            hint: 'کلاس وجود ندارد؛ namespace و autoload را بررسی کنید.',
        );
    }

    public static function notInstantiable(string $abstract): self
    {
        return new self(
            messageId: 'container.not_instantiable',
            context: ['abstract' => $abstract],
            hint: 'Interface/abstract class بدون binding قابل ساخت نیست؛ یک پیاده‌سازی bind کنید.',
        );
    }

    public static function unresolvableParameter(string $concrete, string $parameter): self
    {
        return new self(
            messageId: 'container.unresolvable_parameter',
            context: ['class' => $concrete, 'parameter' => $parameter],
            hint: 'پارامتر نه type کلاسی دارد نه default؛ آن را bind کنید یا با parameters بفرستید.',
        );
    }

    public static function unsafe(string $abstract): self
    {
        return new self(
            messageId: 'container.unsafe_abstract',
            context: ['abstract' => \substr($abstract, 0, 64)],
            hint: '🔒 هرگز abstract را مستقیماً از ورودی کاربر نگیرید.',
        );
    }
}
