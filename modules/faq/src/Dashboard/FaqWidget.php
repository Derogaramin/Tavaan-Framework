<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول پرسش‌های متداول
 * FaqWidget — ویجت داشبورد مدیریت.
 */

namespace Modules\Faq\Dashboard;

use Modules\Faq\Application\UseCases\FaqReadService;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * FaqWidget — Renderable برای پیشخوان: شمار پرسش‌های منتشرشده و دسته‌ها.
 *
 * @api 1.0
 * @see modules/faq/README.md
 */
final class FaqWidget implements Renderable
{
    public function __construct(private readonly FaqReadService $read)
    {
    }

    public function render(): string
    {
        try {
            $stats = $this->read->stats();
            $published = $stats['published'];
            $categories = $stats['categories'];
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>❓ ' . $this->label('widget_title', 'پرسش‌های متداول')
                . '</h3><p class="muted">' . $this->label('widget_unavailable', 'جدول پرسش‌ها آماده نیست (migrate انجام نشده).')
                . '</p></div>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;
        $title = $this->label('widget_title', 'پرسش‌های متداول');
        $publishedCount = $nf($published);
        $categoriesCount = $nf($categories);
        $link = $this->label('widget_link', 'مدیریت پرسش‌ها');

        return <<<HTML
        <div class="admin-widget">
            <h3>❓ {$title}</h3>
            <p class="widget-stats"><strong>{$publishedCount}</strong> منتشرشده • <strong>{$categoriesCount}</strong> دسته</p>
            <p><a class="btn gray" href="/admin/faqs">{$link}</a></p>
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
            $value = \trans('faq::messages.' . $key);

            return \is_string($value) && $value !== '' && $value !== 'faq::messages.' . $key
                ? $value
                : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
