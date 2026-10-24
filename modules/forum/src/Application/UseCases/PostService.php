<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * PostService — نوشتن پاسخ + رأی + پاسخ برگزیده + flag/moderation (DOC-094).
 */

namespace Modules\Forum\Application\UseCases;

use Modules\Content\Domain\Services\Markdown;
use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Entities\Flag;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Modules\Forum\Domain\Repositories\VoteRepositoryInterface;
use Modules\Forum\Domain\Services\FloodControl;
use Modules\Forum\Domain\Services\MentionExtractor;
use Modules\Forum\Domain\Services\QuoteBuilder;
use Modules\Forum\Domain\Services\ReputationCalculator;
use Modules\Forum\Domain\Services\SpamFilter;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * PostService — مسیر نوشتن پست با لایه‌های محافظتی DOC-094 §6/§7:
 *
 * ban → flood control → honeypot → spam heuristic → sanitize → ذخیره →
 * همگام‌سازی شمارنده‌ها → karma → رویداد (search/realtime/notification).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class PostService
{
    /**
     * @param array<string, mixed> $config کلید config('forum')
     */
    public function __construct(
        private readonly PostRepositoryInterface $posts,
        private readonly ThreadRepositoryInterface $threads,
        private readonly ForumRepositoryInterface $forums,
        private readonly VoteRepositoryInterface $votes,
        private readonly FlagRepositoryInterface $flags,
        private readonly ProfileService $profiles,
        private readonly Markdown $markdown,
        private readonly FloodControl $flood,
        private readonly SpamFilter $spam,
        private readonly MentionExtractor $mentions,
        private readonly QuoteBuilder $quotes,
        private readonly ReputationCalculator $reputation,
        private readonly array $config = [],
    ) {
    }

    // ─── ایجاد پاسخ (E2E-01/07) ───────────────────────────────────────────

    /**
     * ثبت پاسخ درون موضوع — با flood/spam/honeypot/ban/lock.
     *
     * @param array<string, mixed> $meta (honeypot?: mixed, parent_id?: string)
     */
    public function createPost(Thread $thread, int $authorId, string $body, array $meta = []): Post
    {
        $this->assertCanWrite($thread, $authorId, $meta);

        $validated = Validator::validate(['body' => $body], [
            'body' => ['required', 'string', 'min:2', 'max:50000'],
        ], attributes: ['body' => 'متن پاسخ']);

        $rawBody = \trim((string) $validated['body']);
        $parentId = isset($meta['parent_id']) ? (string) $meta['parent_id'] : null;
        if ($parentId !== null && ($this->posts->findById($parentId) === null || $parentId === '')) {
            $parentId = null;
        }

        $html = $this->renderBody($rawBody);
        $post = Post::create($thread->id(), $authorId, $html, $parentId !== null && $parentId !== '' ? $parentId : null);
        $this->posts->save($post);

        $this->afterPostCreated($thread, $post, $rawBody);

        return $post;
    }

    /** پست اول موضوع (توسط ThreadService) — بدون شمارش reply. */
    public function createFirstPost(Thread $thread, int $authorId, string $rawBody): Post
    {
        $html = $this->renderBody($rawBody);
        $post = Post::create($thread->id(), $authorId, $html);
        $this->posts->save($post);

        $thread->registerPost($post->createdAt(), isReply: false);
        $this->threads->save($thread);
        $this->refreshForumStats($thread->forumId());

        $this->profiles->addKarma($authorId, $this->reputation->forPostCreated());
        $this->profiles->registerActivity($authorId, thread: false);

        $this->dispatch('forum.post.created', [
            'post_id' => $post->id(),
            'thread_id' => $thread->id(),
            'thread_slug' => $thread->slug(),
            'author_id' => $authorId,
            'parent_id' => null,
            'body' => $rawBody,
            'is_first' => true,
            'mentions' => $this->extractMentions($rawBody, $authorId),
        ]);
        $this->invalidateCache();

        return $post;
    }

    // ─── ویرایش/حذف/پنهان ──────────────────────────────────────────────────

    public function updatePost(Post $post, int $editorId, string $body): Post
    {
        $validated = Validator::validate(['body' => $body], [
            'body' => ['required', 'string', 'min:2', 'max:50000'],
        ], attributes: ['body' => 'متن پست']);

        $post->revise($this->renderBody(\trim((string) $validated['body'])));
        $this->posts->save($post);

        $this->dispatch('forum.post.updated', [
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'author_id' => $post->authorId(),
            'editor_id' => $editorId,
        ]);
        $this->invalidateCache();

        return $post;
    }

    /** پنهان‌کردن پست (moderator — E2E-04). */
    public function hide(Post $post, int $moderatorId, string $reason = ''): Post
    {
        $post->hide();
        $this->posts->save($post);
        $this->recalcThreadStats($post->threadId());

        $this->dispatch('forum.post.hidden', [
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'author_id' => $post->authorId(),
            'moderator_id' => $moderatorId,
            'reason' => $reason !== '' ? $reason : null,
        ]);
        $this->invalidateCache();

        return $post;
    }

    public function unhide(Post $post, int $moderatorId): Post
    {
        $post->unhide();
        $this->posts->save($post);
        $this->recalcThreadStats($post->threadId());

        $this->dispatch('forum.post.updated', [
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'author_id' => $post->authorId(),
            'editor_id' => $moderatorId,
        ]);
        $this->invalidateCache();

        return $post;
    }

    /** حذف نرم پست. */
    public function deletePost(Post $post, int $actorId): void
    {
        $post->softDelete();
        $this->posts->save($post);
        $this->recalcThreadStats($post->threadId());

        $this->dispatch('forum.post.deleted', [
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'author_id' => $post->authorId(),
            'actor_id' => $actorId,
        ]);
        $this->invalidateCache();
    }

    // ─── رأی (E2E-02) ──────────────────────────────────────────────────────

    /**
     * رأی +1/-1 با toggle: رأی تکراری → حذف (لغو).
     *
     * @return array{value: int|null, score: int} value = رأی فعلی (null یعنی لغو شد)
     */
    public function vote(Post $post, int $voterId, int $value): array
    {
        if (!$post->isVisible()) {
            throw ForumException::postNotVisible();
        }
        if ($post->authorId() === $voterId) {
            throw ForumException::cannotVoteOwnPost();
        }
        $this->profiles->assertNotBanned($voterId);

        $value = $value >= 0 ? 1 : -1;
        $current = $this->votes->currentVote($post->id(), $voterId);

        if ($current === $value) {
            // toggle → لغو رأی
            $this->votes->remove($post->id(), $voterId);
            $newValue = null;
        } else {
            $this->votes->cast($post->id(), $voterId, $value);
            $newValue = $value;
        }

        $score = $this->votes->scoreOf($post->id());
        $post->applyVoteScore($score);
        $this->posts->save($post);

        // 🔒 Reputation/Karma (DOC-094 §6): delta = f(new) - f(old)
        $delta = $this->reputation->forVoteReceived($newValue ?? 0)
            - $this->reputation->forVoteReceived($current ?? 0);
        if ($delta !== 0) {
            $this->profiles->ensureProfile($post->authorId());
            $this->profiles->addKarma($post->authorId(), $delta);
        }

        $this->dispatch('forum.post.voted', [
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'post_author_id' => $post->authorId(),
            'voter_id' => $voterId,
            'value' => $newValue ?? 0,
            'old_value' => $current ?? 0,
            'score' => $score,
        ]);

        return ['value' => $newValue, 'score' => $score];
    }

    // ─── پاسخ برگزیده (E2E-03) ─────────────────────────────────────────────

    /**
     * انتخاب/برداشتن پاسخ برگزیده — توسط نویسندهٔ موضوع یا دارندهٔ
     * `forum.post.solution` (کنترل دسترسی در Policy؛ اینجا همگام‌سازی state).
     */
    public function markSolution(Thread $thread, Post $post, int $actorId, bool $marked = true): Post
    {
        if (!$post->isVisible() || $post->threadId() !== $thread->id()) {
            throw ForumException::solutionNotAllowed();
        }

        if ($marked) {
            // برگزیدهٔ قبلی (اگر هست) برداشته می‌شود
            foreach ($this->posts->allVisibleForThread($thread->id()) as $candidate) {
                if ($candidate->isSolution() && $candidate->id() !== $post->id()) {
                    $candidate->markSolution(false);
                    $this->posts->save($candidate);
                    $this->profiles->addKarma($candidate->authorId(), $this->reputation->forSolution(false));
                    $this->profiles->registerSolution($candidate->authorId(), false);
                }
            }
            if (!$post->isSolution()) {
                $post->markSolution(true);
                $this->posts->save($post);
                $this->profiles->addKarma($post->authorId(), $this->reputation->forSolution(true));
                $this->profiles->registerSolution($post->authorId(), true);
            }
            $thread->setSolved(true);
        } else {
            if ($post->isSolution()) {
                $post->markSolution(false);
                $this->posts->save($post);
                $this->profiles->addKarma($post->authorId(), $this->reputation->forSolution(false));
                $this->profiles->registerSolution($post->authorId(), false);
            }
            $thread->setSolved($this->hasSolution($thread->id()));
        }

        $this->threads->save($thread);

        $this->dispatch('forum.post.solved', [
            'post_id' => $post->id(),
            'thread_id' => $thread->id(),
            'post_author_id' => $post->authorId(),
            'thread_author_id' => $thread->authorId(),
            'marked' => $marked && $post->isSolution(),
            'actor_id' => $actorId,
        ]);
        $this->invalidateCache();

        return $post;
    }

    // ─── flag / صف moderation (E2E-04) ─────────────────────────────────────

    /** گزارش پست → صف moderation (DOC-094 §5: forum.post.flagged). */
    public function flag(Post $post, ?int $reporterId, string $reason = ''): int
    {
        if (!$post->isVisible()) {
            throw ForumException::alreadyFlagged();
        }

        if ((bool) ($this->config['moderation']['flag_once_per_user'] ?? true)
            && $reporterId !== null
            && $this->flags->hasOpenByUser($post->id(), $reporterId)) {
            throw ForumException::alreadyFlagged();
        }

        $flag = Flag::report($post->id(), $reporterId, $reason);
        $this->flags->save($flag);

        $this->dispatch('forum.post.flagged', [
            'flag_id' => $flag->id() ?? 0,
            'post_id' => $post->id(),
            'thread_id' => $post->threadId(),
            'post_author_id' => $post->authorId(),
            'reporter_id' => $reporterId,
            'reason' => $flag->reason(),
        ]);

        // auto-hide با آستانهٔ گزارش
        $threshold = (int) ($this->config['moderation']['auto_hide_threshold'] ?? 0);
        if ($threshold > 0 && $this->flags->countOpenForPost($post->id()) >= $threshold) {
            $this->hide($post, $reporterId ?? 0, 'auto:flag_threshold');
        }

        return (int) ($flag->id() ?? 0);
    }

    /** نقل‌قول markdown برای prefill فرم پاسخ (DOC-094 §6). */
    public function quote(Post $post): string
    {
        return $this->quotes->build(
            $post->body(),
            $this->profiles->usernameOf($post->authorId()),
        );
    }

    /** همگام‌سازی شمارنده‌های تالار (ورودی عمومی برای ThreadService/Command). */
    public function refreshForumStatsPublic(int $forumId): void
    {
        $this->refreshForumStats($forumId);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $meta */
    private function assertCanWrite(Thread $thread, int $authorId, array $meta): void
    {
        if (!$thread->acceptsReplies()) {
            throw ForumException::threadLocked();
        }
        $this->profiles->assertNotBanned($authorId);

        // honeypot (DOC-094 §7) — فیلد نامرئی باید خالی بماند
        $honeypotField = (string) ($this->config['spam']['honeypot_field'] ?? 'website');
        if (\array_key_exists($honeypotField, $meta) && \trim((string) $meta[$honeypotField]) !== '') {
            throw ForumException::honeypot();
        }

        // flood control (E2E-07)
        if ($this->flood->enabled()) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $window = \max(1, (int) ($this->config['flood']['window_seconds'] ?? 300));
            $recent = $this->posts->recentPostTimesByAuthor($authorId, $now->sub(new \DateInterval('PT' . $window . 'S')));
            $decision = $this->flood->decide($recent, $now);
            if (!$decision['allowed']) {
                throw ForumException::floodBlocked((int) $decision['retry_after'], (string) $decision['reason']);
            }
        }
    }

    private function afterPostCreated(Thread $thread, Post $post, string $rawBody): void
    {
        $thread->registerPost($post->createdAt());
        $this->threads->save($thread);
        $this->refreshForumStats($thread->forumId());

        $this->profiles->addKarma($post->authorId(), $this->reputation->forPostCreated());
        $this->profiles->registerActivity($post->authorId(), thread: false);

        $this->dispatch('forum.post.created', [
            'post_id' => $post->id(),
            'thread_id' => $thread->id(),
            'thread_slug' => $thread->slug(),
            'author_id' => $post->authorId(),
            'parent_id' => $post->parentId(),
            'body' => $rawBody,
            'is_first' => false,
            'mentions' => $this->extractMentions($rawBody, $post->authorId()),
        ]);
        $this->invalidateCache();
    }

    /** markdown/html → HTML سانیتیزه‌شده (دو لایه — هم‌راستا با blog/news). */
    private function renderBody(string $raw): string
    {
        $raw = \trim($raw);
        if ($raw !== '' && $this->spam->isSpam($raw)) {
            throw ForumException::spamDetected();
        }

        $format = (string) ($this->config['body_format'] ?? 'markdown');
        $html = $format === 'html' ? $raw : $this->markdown->toHtml($raw);

        $profile = (string) ($this->config['sanitize']['profile'] ?? 'rich_text');
        $extraTags = (array) ($this->config['sanitize']['extra_tags'] ?? []);

        return Security::sanitize($html, $profile, [
            'allowed_tags' => [...(array) (\function_exists('config') ? (config('security.sanitizers.' . $profile . '.allowed_tags') ?? []) : []), ...$extraTags],
        ]);
    }

    /** @return list<int> شناسهٔ کاربران mention‌شده (به‌جز نویسنده) */
    private function extractMentions(string $rawBody, int $excludeUserId): array
    {
        if (!(bool) ($this->config['mentions']['enabled'] ?? true)) {
            return [];
        }

        $ids = [];
        foreach ($this->mentions->extract($rawBody) as $username) {
            $profile = $this->profiles->findByUsername($username);
            if ($profile !== null && $profile->userId() !== $excludeUserId) {
                $ids[] = $profile->userId();
            }
        }

        return \array_values(\array_unique($ids));
    }

    /** همگام‌سازی reply_count/last_post_at/is_solved موضوع از روی پست‌ها. */
    private function recalcThreadStats(string $threadId): void
    {
        $thread = $this->threads->findById($threadId);
        if ($thread === null) {
            return;
        }

        $firstId = $this->posts->firstPostOfThread($threadId)?->id();
        $visible = $this->posts->allVisibleForThread($threadId);
        $last = null;
        $replies = 0;
        foreach ($visible as $post) {
            if ($post->id() === $firstId) {
                continue;   // پست اول reply نیست
            }
            $replies++;
            $last = $post->createdAt();
        }

        $thread->applyStats($replies, $last, $this->hasSolution($threadId));
        $this->threads->save($thread);
        $this->refreshForumStats($thread->forumId());
    }

    private function hasSolution(string $threadId): bool
    {
        foreach ($this->posts->allVisibleForThread($threadId) as $post) {
            if ($post->isSolution()) {
                return true;
            }
        }

        return false;
    }

    /** بازخوانی شمارنده‌های تالار (thread_count/post_count/last_post_id). */
    private function refreshForumStats(int $forumId): void
    {
        $forum = $this->forums->findById($forumId);
        if ($forum === null) {
            return;
        }

        $stats = $this->posts->forumPostStats($forumId);
        $forum->applyStats(
            $this->threads->countForForum($forumId),
            (int) $stats['count'],
            $stats['last_post_id'],
        );
        $this->forums->save($forum);
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $event, array $payload): void
    {
        Event::dispatch($event, $payload);
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
