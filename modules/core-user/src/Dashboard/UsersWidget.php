<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UsersWidget — ویجت داشبورد مدیریت (آمار کاربران + تازه‌ترین ثبت‌نام‌ها).
 */

namespace Modules\CoreUser\Dashboard;

use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Support\Renderable;

/**
 * UsersWidget — Renderable برای پیشخوان (DOC-078 §5).
 *
 * خطای ویجت توسط Dashboard ایزوله می‌شود؛ اینجا هم دفاعی کار می‌کنیم
 * (جدول users ممکن است هنوز مهاجرت نشده باشد).
 *
 * @api 1.0
 */
final class UsersWidget implements Renderable
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function render(): string
    {
        try {
            $total = $this->users->countAll();
            $active = $this->users->countByStatus(UserStatus::Active);
            $banned = $this->users->countByStatus(UserStatus::Banned);
            $latest = $this->users->latest(5);
        } catch (\Throwable) {
            return '<div class="admin-widget"><h3>👥 کاربران</h3><p class="muted">جدول کاربران آماده نیست (migrate انجام نشده).</p></div>';
        }

        $rows = '';
        foreach ($latest as $user) {
            $rows .= '<li>' . \htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8')
                . ' <span class="muted">— ' . \htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') . '</span></li>';
        }

        $nf = static fn (int $n): string => \function_exists('trans_number') ? \trans_number($n) : (string) $n;

        return <<<HTML
        <div class="admin-widget">
            <h3>👥 کاربران</h3>
            <p class="widget-stats">
                <strong>{$nf($total)}</strong> کل •
                <strong>{$nf($active)}</strong> فعال •
                <strong>{$nf($banned)}</strong> مسدود
            </p>
            <ul class="widget-list">{$rows}</ul>
            <p><a class="btn gray" href="/admin/users">مدیریت کاربران</a></p>
        </div>
        HTML;
    }
}
