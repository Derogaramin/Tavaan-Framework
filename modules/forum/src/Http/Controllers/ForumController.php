<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumController — صفحات عمومی انجمن (DOC-094 §3).
 */

namespace Modules\Forum\Http\Controllers;

use Modules\Forum\Application\UseCases\ForumReadService;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;

/**
 * ForumController — فهرست تالارها / موضوعات یک تالار / جست‌وجو.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumController
{
    public function __construct(
        private readonly ForumReadService $read,
        private readonly ForumRepositoryInterface $forums,
    ) {
    }

    /** GET /forums — درخت تالارها + آخرین موضوعات + اعضای برتر. */
    public function index(): mixed
    {
        $tree = $this->read->forumTree();

        return view('forum::index', [
            'roots' => $tree['roots'],
            'stats' => $tree['stats'],
            'latest' => $this->read->latestThreads(8),
            'topMembers' => $this->read->topMembers(5),
            'heading' => trans('forum::messages.forums'),
        ]);
    }

    /** GET /forums/search?q= — جست‌وجوی فارسی (E2E-05). */
    public function search(RequestInterface $request): mixed
    {
        $query = \trim((string) $request->query('q', ''));
        $page = \max(1, (int) $request->query('page', 1));

        $result = $this->read->search($query, $page);

        return view('forum::search', [
            'result' => $result,
            'query' => $query,
            'topMembers' => $this->read->topMembers(5),
        ]);
    }

    /** GET /forums/{slug} — موضوعات یک تالار (با زیرتالارها). */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $forum = $this->forums->findBySlug($slug);
        if ($forum === null) {
            throw RouteNotFoundException::forPath('GET', '/forums/' . $slug);
        }

        $page = \max(1, (int) $request->query('page', 1));
        $result = $this->read->threadsForForum($forum, $page);

        return view('forum::forum', [
            'forum' => $forum,
            'threads' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'children' => \array_values(\array_filter(
                $this->forums->all(),
                static fn ($f): bool => $f->parentId() === $forum->id(),
            )),
            'topMembers' => $this->read->topMembers(5),
        ]);
    }
}
