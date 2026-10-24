<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumPolicy — مجوزهای تالار (DOC-094 §4: admin تالارها را مدیریت می‌کند).
 */

namespace Modules\Forum\Policies;

use Modules\Forum\Domain\Entities\Forum;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * ForumPolicy — از راه `Gate::authorize('<ability>', $forum)`.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumPolicy
{
    /** تالارها عمومی‌اند. */
    public function view(Authenticatable $user, Forum $forum): bool
    {
        return true;
    }

    /** ساخت موضوع در تالار: member + تالار باز. */
    public function create(Authenticatable $user, Forum $forum): bool
    {
        return Gate::allows('forum.thread.create') && !$forum->isLocked();
    }

    public function update(Authenticatable $user, Forum $forum): bool
    {
        return Gate::allows('forum.forum.manage');
    }

    public function delete(Authenticatable $user, Forum $forum): bool
    {
        return Gate::allows('forum.forum.manage');
    }
}
