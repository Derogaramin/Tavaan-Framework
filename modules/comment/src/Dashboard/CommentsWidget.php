<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentsWidget — ویجت داشبورد مدیریت (صف بررسی نظرات).
 */

namespace Modules\Comment\Dashboard;

use Modules\Comment\Application\UseCases\ModerationService;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * CommentsWidget — Renderable برای پیشخوان: آمار pending/approved/spam.
 *
 * @api 1.0
 */
final class CommentsWidget implements Renderable
{
    public function __construct(private readonly ModerationService $moderation)
    {
    }

    public function render(): string
    {
        try {
            $stats = $this->moderation->stats();
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>💬 نظرات</h3><p class="muted">جدول نظرات آماده نیست (migrate انجام نشده).</p></div>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;
        $pending = $nf($stats['pending']);
        $approved = $nf($stats['approved']);
        $spam = $nf($stats['spam']);
        $total = $nf($stats['total']);

        $alert = $stats['pending'] > 0
            ? '<p style="color:#92400e">⏳ ' . $pending . ' نظر در صف بررسی</p>'
            : '<p class="muted">صف بررسی خالی است ✓</p>';

        return <<<HTML
        <div class="admin-widget">
            <h3>💬 نظرات</h3>
            <p class="widget-stats"><strong>{$total}</strong> کل • <strong>{$approved}</strong> تأییدشده • <strong>{$spam}</strong> اسپم</p>
            {$alert}
            <p><a class="btn gray" href="/admin/comments">صف بررسی</a></p>
        </div>
        HTML;
    }
}
