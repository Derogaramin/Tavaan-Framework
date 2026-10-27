<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaWidget — ویجت داشبورد مدیریت (آمار کتابخانهٔ رسانه).
 */

namespace Modules\Media\Dashboard;

use Tavaan\Core\Contracts\Support\Renderable;
use Tavaan\Core\Facade\Facades\Media;

/**
 * MediaWidget — Renderable برای پیشخوان: شمار رسانه‌ها + تازه‌ترین‌ها.
 *
 * @api 1.0
 */
final class MediaWidget implements Renderable
{
    public function render(): string
    {
        try {
            $all = Media::paginate('', '', 1, 5);
            $total = (int) $all['total'];

            $thumbs = '';
            foreach ($all['rows'] as $media) {
                if (\str_starts_with((string) $media->mimeType, 'image/')) {
                    $thumbs .= '<img src="' . \htmlspecialchars((string) $media->url(), ENT_QUOTES, 'UTF-8')
                        . '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;margin:2px">';
                } else {
                    $thumbs .= '<span style="display:inline-block;width:44px;height:44px;line-height:44px;text-align:center;background:#f1f5f9;border-radius:6px;margin:2px">📄</span>';
                }
            }
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>🖼️ رسانه</h3><p class="muted">کتابخانهٔ رسانه آماده نیست.</p></div>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;

        return <<<HTML
        <div class="admin-widget">
            <h3>🖼️ رسانه</h3>
            <p class="widget-stats"><strong>{$nf($total)}</strong> فایل در کتابخانه</p>
            <div>{$thumbs}</div>
            <p><a class="btn gray" href="/admin/media">کتابخانهٔ رسانه</a></p>
        </div>
        HTML;
    }
}
