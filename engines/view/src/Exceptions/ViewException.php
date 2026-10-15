<?php

declare(strict_types=1);

namespace Tavaan\Engine\View\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * پایهٔ خطاهای View (حوزهٔ کد: VW).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
class ViewException extends TavaanException
{
    public static function notFound(string $template): self
    {
        return new self('view.not_found', ['template' => $template], 'نام قالب و مسیرهای ثبت‌شده را بررسی کنید.');
    }

    public static function unknownDirective(string $directive): self
    {
        return new self('view.directive.unknown', ['directive' => $directive], 'آیا منظور شما یکی از directiveهای ثبت‌شده بود؟');
    }

    public static function compilationFailed(string $template, string $reason): self
    {
        return new self('view.compilation_failed', ['template' => $template, 'reason' => \substr($reason, 0, 200)]);
    }

    public static function maxDepth(string $template, int $depth): self
    {
        return new self('view.max_depth', ['template' => $template, 'depth' => $depth], 'include/@extends تودرتو بیش از حد — حلقهٔ ویو را بشکنید.');
    }
}
