<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumReadService — خواندن: درخت تالارها، موضوعات، صفحهٔ موضوع، جست‌وجو.
 */

namespace Modules\Forum\Application\UseCases;

use Modules\Forum\Domain\Entities\Forum;
use Modules\Forum\Domain\Entities\Post;
use Modules\Forum\Domain\Entities\Thread;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;
use Modules\Forum\Domain\Repositories\SubscriptionRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Search;

/**
 * ForumReadService — پرس‌وجوهای خواندنی + کش تگ‌محور + جست‌وجوی فارسی
 * (ایندکس موتور search با fallback LIKE — هم‌راستا با shop/news).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumReadService
{
    /**
     * @param array<string, mixed> $config کلید config('forum')
     */
    public function __construct(
        private readonly ForumRepositoryInterface $forums,
        private readonly ThreadRepositoryInterface $threads,
        private readonly PostRepositoryInterface $posts,
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly ProfileService $profiles,
        private readonly array $config = [],
    ) {
    }

    // ─── تالارها ───────────────────────────────────────────────────────────

    /**
     * درخت تالارها برای صفحهٔ نخست: ریشه‌ها با زیرتالارها + آمار کلی.
     *
     * @return array{roots: list<array{forum: Forum, children: list<Forum>}>, stats: array{forums: int, threads: int, posts: int, members: int}}
     */
    public function forumTree(): array
    {
        return $this->remember('tree', function (): array {
            $all = $this->forums->all();
            $byParent = [];
            $roots = [];
            $threadTotal = 0;
            $postTotal = 0;
            foreach ($all as $forum) {
                $threadTotal += $forum->threadCount();
                $postTotal += $forum->postCount();
                $parent = $forum->parentId();
                if ($parent === null) {
                    $roots[] = $forum;
                } else {
                    $byParent[$parent][] = $forum;
                }
            }

            $tree = [];
            foreach ($roots as $root) {
                $tree[] = [
                    'forum' => $root,
                    'children' => $byParent[$root->id() ?? -1] ?? [],
                ];
            }

            return [
                'roots' => $tree,
                'stats' => [
                    'forums' => \count($all),
                    'threads' => $threadTotal,
                    'posts' => $postTotal,
                    'members' => $this->profiles->countMembers(),
                ],
            ];
        });
    }

    // ─── موضوعات یک تالار ──────────────────────────────────────────────────

    /**
     * صفحه‌بندی موضوعات تالار (با زیرتالارها) — pinned نخست.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{rows: list<Thread>, total: int, page: int, perPage: int}
     */
    public function threadsForForum(Forum $forum, int $page = 1, array $filters = []): array
    {
        $forumIds = $this->forums->withDescendants((int) $forum->id());
        $perPage = (int) ($this->config['per_page'] ?? 20);
        $result = $this->threads->paginate($forumIds, $filters, $page, $perPage);

        return [...$result, 'page' => $page, 'perPage' => $perPage];
    }

    /** آخرین موضوعات فعال — sidebar صفحهٔ نخست. */
    public function latestThreads(int $limit = 8): array
    {
        return $this->threads->latest($limit);
    }

    // ─── صفحهٔ موضوع ───────────────────────────────────────────────────────

    /**
     * صفحهٔ موضوع: پست اول + پاسخ‌های صفحه‌بندی‌شده + نام‌ها + وضعیت اشتراک.
     *
     * @return array{thread: Thread, first: ?Post, result: array{rows: list<Post>, total: int}, names: array<string, string>, usernames: array<string, string>, subscribed: bool}
     */
    public function threadPage(Thread $thread, int $page = 1, ?int $viewerId = null, bool $includeHidden = false): array
    {
        $perPage = (int) ($this->config['posts_per_page'] ?? 20);
        $first = $this->posts->firstPostOfThread($thread->id());
        $statuses = $includeHidden
            ? [\Modules\Forum\Domain\Enums\PostStatus::Visible, \Modules\Forum\Domain\Enums\PostStatus::Hidden]
            : null;
        $result = $this->posts->paginateForThread($thread->id(), $page, $perPage, $statuses);

        $authorIds = [];
        foreach ($result['rows'] as $post) {
            $authorIds[] = $post->authorId();
        }
        if ($first !== null) {
            $authorIds[] = $first->authorId();
        }
        $authorIds[] = $thread->authorId();

        $display = $this->profiles->displayNames($authorIds);

        return [
            'thread' => $thread,
            'first' => $first,
            'result' => [...$result, 'page' => $page, 'perPage' => $perPage],
            'names' => $display['names'],
            'usernames' => $display['usernames'],
            'subscribed' => $viewerId !== null && $this->subscriptions->isSubscribed($viewerId, $thread->id()),
        ];
    }

    /** پست‌های یک صفحه شامل first (برای ویو). */
    public function postAuthorName(Post $post, array $names): string
    {
        return $names[(string) $post->authorId()] ?? 'کاربر';
    }

    // ─── جست‌وجو (E2E-05) ──────────────────────────────────────────────────

    /**
     * جست‌وجوی فارسی در موضوعات + پست‌ها.
     *
     * @return array{threads: list<Thread>, posts: list<Post>, query: string, page: int, usedIndex: bool}
     */
    public function search(string $query, int $page = 1): array
    {
        $normalized = $this->normalizeQuery($query);
        $perPage = (int) ($this->config['per_page'] ?? 20);

        $threadIds = [];
        $postIds = [];

        if ((bool) ($this->config['search']['enabled'] ?? true) && $normalized !== '') {
            // هر ایندکس جدا try می‌شود — خرابی یکی، دیگری را نمی‌اندازد
            try {
                $threadIds = $this->hits((string) ($this->config['search']['threads_index'] ?? 'forum_threads'), $normalized);
            } catch (\Throwable) {
                $threadIds = [];
            }
            try {
                $postIds = $this->hits((string) ($this->config['search']['posts_index'] ?? 'forum_posts'), $normalized);
            } catch (\Throwable) {
                $postIds = [];
            }
        }

        if ($threadIds !== [] || $postIds !== []) {
            $threads = [];
            foreach ($threadIds as $id) {
                $thread = $this->threads->findById($id);
                if ($thread !== null) {
                    $threads[] = $thread;
                }
            }
            $posts = [];
            foreach ($postIds as $id) {
                $post = $this->posts->findById($id);
                if ($post !== null && $post->isVisible()) {
                    $posts[] = $post;
                }
            }

            // 🔒 hitهای کهنه (سند ایندکس بدون ردیف DB) → نتیجهٔ خالی یعنی
            // ایندکس قابل اتکا نبود → fallback LIKE (هم‌راستا با shop/news).
            if ($threads !== [] || $posts !== []) {
                return ['threads' => $threads, 'posts' => $posts, 'query' => $query, 'page' => 1, 'usedIndex' => true];
            }
        }

        // fallback: LIKE روی DB — با هر دو واریانت رقم (فارسی/لاتین)
        $threadRows = [];
        $postRows = [];
        foreach ($this->digitVariants($normalized) as $variant) {
            foreach ($this->threads->searchLike($variant, $page, $perPage)['rows'] as $thread) {
                $threadRows[$thread->id()] = $thread;
            }
            foreach ($this->posts->searchLike($variant, $page, $perPage)['rows'] as $post) {
                $postRows[$post->id()] = $post;
            }
        }

        return [
            'threads' => \array_values($threadRows),
            'posts' => \array_values($postRows),
            'query' => $query,
            'page' => $page,
            'usedIndex' => false,
        ];
    }

    // ─── اعضا ──────────────────────────────────────────────────────────────

    public function topMembers(int $limit = 5): array
    {
        return $this->profiles->topMembers($limit);
    }

    public function countMembers(): int
    {
        return $this->profiles->countMembers();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** نرمال‌سازی پرس‌وجوی فارسی: ارقام فارسی/عربی → لاتین، ی/ک یکسان. */
    private function normalizeQuery(string $query): string
    {
        $query = \trim($query);
        $query = \strtr($query, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه',
        ]);

        return (string) \preg_replace('/\s+/u', ' ', $query);
    }

    /**
     * واریانت‌های رقمی پرس‌وجو — متن ذخیره‌شده ممکن است رقم فارسی داشته
     * باشد و پرس‌وجوی کاربر لاتین (یا برعکس).
     *
     * @return list<string>
     */
    private function digitVariants(string $query): array
    {
        $persian = \strtr($query, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);

        return $persian === $query ? [$query] : [$query, $persian];
    }

    /** @return list<string> شناسه‌های hit */
    private function hits(string $index, string $query): array
    {
        $searchResult = Search::search($index, $query, ['limit' => 50]);
        $ids = [];
        foreach ((array) (\method_exists($searchResult, 'hits') ? $searchResult->hits() : []) as $hit) {
            $id = \is_array($hit) ? ($hit['id'] ?? null) : null;
            if (\is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** کش با tag انجمن (config forum.cache). */
    private function remember(string $key, \Closure $callback): mixed
    {
        if (!(bool) ($this->config['cache']['enabled'] ?? true)) {
            return $callback();
        }

        try {
            $tag = (string) ($this->config['cache']['tag'] ?? 'forum');
            $ttl = (int) ($this->config['cache']['ttl'] ?? 60);

            return Cache::tags([$tag])->remember('forum:' . $key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
