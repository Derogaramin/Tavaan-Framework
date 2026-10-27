<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollsWidget — ویجت داشبورد مدیریت.
 */

namespace Modules\Poll\Dashboard;

use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * PollsWidget — Renderable برای پیشخوان: نظرسنجی‌های باز + کل رأی‌ها.
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollsWidget implements Renderable
{
    public function __construct(
        private readonly PollRepositoryInterface $polls,
        private readonly VoteRepositoryInterface $votes,
    ) {
    }

    public function render(): string
    {
        try {
            $open = $this->polls->countOpen();
            $totalVotes = $this->votes->countAll();
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>📊 ' . $this->label('widget_title', 'نظرسنجی')
                . '</h3><p class="muted">' . $this->label('widget_unavailable', 'جدول نظرسنجی آماده نیست (migrate انجام نشده).')
                . '</p></div>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;
        $title = $this->label('widget_title', 'نظرسنجی');
        $openCount = $nf($open);
        $votesCount = $nf($totalVotes);
        $link = $this->label('widget_link', 'مدیریت نظرسنجی‌ها');

        return <<<HTML
        <div class="admin-widget">
            <h3>📊 {$title}</h3>
            <p class="widget-stats"><strong>{$openCount}</strong> باز • <strong>{$votesCount}</strong> رأی</p>
            <p><a class="btn gray" href="/admin/polls">{$link}</a></p>
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
            $value = \trans('poll::messages.' . $key);

            return \is_string($value) && $value !== '' && $value !== 'poll::messages.' . $key
                ? $value
                : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
