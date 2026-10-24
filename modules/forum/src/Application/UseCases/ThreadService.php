<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ThreadService — ساخت/مدیریت موضوع + اشتراک (DOC-094 §3/§5).
 */

namespace Modules\Forum\Application\UseCases;

use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Forum\Domain\Entities\Forum;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\SubscriptionRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Modules\Forum\Domain\Repositories\VoteRepositoryInterface;
use Modules\Forum\Domain\Services\ReputationCalculator;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ThreadService — موضوع جدید (با پست اول)، قفل/سنجاق/جابجایی/حذف و اشتراک.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ThreadService
{
    /**
     * @param array<string, mixed> $config کلید config('forum')
     */
    public function __construct(
        private readonly ThreadRepositoryInterface $threads,
        private readonly PostRepositoryInterface $posts,
        private readonly ForumRepositoryInterface $forums,
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly VoteRepositoryInterface $votes,
        private readonly FlagRepositoryInterface $flags,
        private readonly ProfileService $profiles,
        private readonly PostService $postService,
        private readonly ReputationCalculator $reputation,
        private readonly array $config = [],
    ) {
    }

    // ─── ساخت موضوع (E2E-01) ───────────────────────────────────────────────

    /**
     * ساخت موضوع + پست اول در یک تالار.
     *
     * @param array<string, mixed> $input (title, body)
     *
     * @return array{thread: Thread, post: \Modules\Forum\Domain\Entities\Post}
     */
    public function createThread(Forum $forum, int $authorId, array $input): array
    {
        if ($forum->isLocked()) {
            throw ForumException::forumLocked();
        }
        $this->profiles->assertNotBanned($authorId);

        $validated = Validator::validate($input, [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'body' => ['required', 'string', 'min:2', 'max:50000'],
        ], attributes: ['title' => 'عنوان', 'body' => 'متن']);

        $title = \trim((string) $validated['title']);
        $slug = $this->resolveSlug($title);

        $thread = Thread::create($forum->id() ?? 0, $authorId, $title, $slug);
        $this->threads->save($thread);

        $post = $this->postService->createFirstPost($thread, $authorId, \trim((string) $validated['body']));

        // اشتراک خودکار نویسنده (DOC-094: اعلان به دنبال‌کنندگان)
        if ((bool) ($this->config['subscriptions']['auto_subscribe_author'] ?? true)) {
            $this->subscriptions->subscribe($authorId, $thread->id());
        }

        $this->profiles->addKarma($authorId, $this->reputation->forThreadCreated());
        $this->profiles->registerActivity($authorId, thread: true);

        Event::dispatch('forum.thread.created', [
            'thread_id' => $thread->id(),
            'forum_id' => $thread->forumId(),
            'title' => $thread->title(),
            'slug' => $thread->slug(),
            'author_id' => $authorId,
            'first_post_id' => $post->id(),
        ]);
        $this->invalidateCache();

        return ['thread' => $thread, 'post' => $post];
    }

    // ─── مدیریت موضوع (moderator) ──────────────────────────────────────────

    public function rename(Thread $thread, int $actorId, string $title): Thread
    {
        $validated = Validator::validate(['title' => $title], [
            'title' => ['required', 'string', 'min:5', 'max:255'],
        ], attributes: ['title' => 'عنوان']);

        $thread->rename(\trim((string) $validated['title']));
        $this->threads->save($thread);
        $this->dispatchUpdated($thread, 'title', $actorId);

        return $thread;
    }

    public function setLocked(Thread $thread, bool $locked, int $actorId): Thread
    {
        $thread->setLocked($locked);
        $this->threads->save($thread);
        $this->dispatchUpdated($thread, $locked ? 'lock' : 'unlock', $actorId);
        $this->invalidateCache();

        return $thread;
    }

    public function setPinned(Thread $thread, bool $pinned, int $actorId): Thread
    {
        $thread->setPinned($pinned);
        $this->threads->save($thread);
        $this->dispatchUpdated($thread, $pinned ? 'pin' : 'unpin', $actorId);
        $this->invalidateCache();

        return $thread;
    }

    /** جابجایی موضوع بین تالارها (DOC-094 §4: moderator). */
    public function move(Thread $thread, int $targetForumId, int $actorId): Thread
    {
        $target = $this->forums->findById($targetForumId);
        if ($target === null) {
            throw new \InvalidArgumentException('تالار مقصد یافت نشد.');
        }

        $originForumId = $thread->forumId();
        $thread->moveTo($targetForumId);
        $this->threads->save($thread);

        $this->postService->refreshForumStatsPublic($originForumId);
        $this->postService->refreshForumStatsPublic($targetForumId);

        $this->dispatchUpdated($thread, 'move', $actorId);
        $this->invalidateCache();

        return $thread;
    }

    /** حذف موضوع + همهٔ پست‌ها/رأی‌ها/اشتراک‌ها/گزارش‌ها (admin/moderator). */
    public function deleteThread(Thread $thread, int $actorId): void
    {
        foreach ($this->posts->idsForThread($thread->id()) as $postId) {
            $this->votes->deleteForPost($postId);
            $this->flags->deleteForPost($postId);
        }
        $this->posts->deleteForThread($thread->id());
        $this->subscriptions->deleteForThread($thread->id());

        $forumId = $thread->forumId();
        $this->threads->delete($thread->id());
        $this->postService->refreshForumStatsPublic($forumId);

        Event::dispatch('forum.thread.updated', [
            'thread_id' => $thread->id(),
            'slug' => $thread->slug(),
            'title' => $thread->title(),
            'change' => 'delete',
            'actor_id' => $actorId,
        ]);
        $this->invalidateCache();
    }

    /** ثبت بازدید (بدون load/save کامل — increment اتمیک). */
    public function registerView(Thread $thread): void
    {
        $this->threads->incrementViews($thread->id());
    }

    // ─── اشتراک (DOC-094 §2: forum_subscriptions) ──────────────────────────

    public function subscribe(int $userId, Thread $thread): void
    {
        $this->subscriptions->subscribe($userId, $thread->id());
    }

    public function unsubscribe(int $userId, Thread $thread): void
    {
        $this->subscriptions->unsubscribe($userId, $thread->id());
    }

    public function isSubscribed(int $userId, Thread $thread): bool
    {
        return $this->subscriptions->isSubscribed($userId, $thread->id());
    }

    /** @return list<int> */
    public function subscribersOf(Thread $thread): array
    {
        return $this->subscriptions->subscribersOf($thread->id());
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function resolveSlug(string $title): string
    {
        $base = Slug::slugify($title);
        if ($base === '') {
            $base = 'thread';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->threads->slugExists($candidate))->toString();
    }

    private function dispatchUpdated(Thread $thread, string $change, int $actorId): void
    {
        Event::dispatch('forum.thread.updated', [
            'thread_id' => $thread->id(),
            'slug' => $thread->slug(),
            'title' => $thread->title(),
            'change' => $change,
            'actor_id' => $actorId,
        ]);
    }

    private function invalidateCache(): void
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true)) {
            return;
        }

        try {
            Cache::tags([(string) ($this->config['cache']['tag'] ?? 'forum')])->flush();
        } catch (\Throwable) {
            // درایور بدون tag
        }
    }
}
