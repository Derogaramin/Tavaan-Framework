<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * PagesWidget — ویجت داشبورد مدیریت (صفحات CMS).
 */

namespace Modules\Content\Dashboard;

use Modules\Content\Domain\Repositories\PageRepositoryInterface;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * PagesWidget — Renderable برای پیشخوان: شمار صفحات منتشرشده.
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class PagesWidget implements Renderable
{
    public function __construct(private readonly PageRepositoryInterface $pages)
    {
    }

    public function render(): string
    {
        try {
            $published = $this->pages->countPublished();
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>📄 ' . $this->label('widget_title', 'صفحات')
                . '</h3><p class="muted">' . $this->label('widget_unavailable', 'جدول صفحات آماده نیست (migrate انجام نشده).')
                . '</p></div>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;
        $count = $nf($published);
        $title = $this->label('widget_title', 'صفحات');
        $link = $this->label('widget_link', 'مدیریت صفحات');

        return <<<HTML
        <div class="admin-widget">
            <h3>📄 {$title}</h3>
            <p class="widget-stats"><strong>{$count}</strong> منتشرشده</p>
            <p><a class="btn gray" href="/admin/pages">{$link}</a></p>
        </div>
        HTML;
    }

    /** ترجمه با fallback متنی (ویجت ممکن است پیش از بارگذاری lang رندر شود). */
    private function label(string $key, string $fallback): string
    {
        if (!\function_exists('trans')) {
            return $fallback;
        }

        try {
            $value = \trans('content::messages.' . $key);

            return \is_string($value) && $value !== '' && $value !== 'content::messages.' . $key
                ? $value
                : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
