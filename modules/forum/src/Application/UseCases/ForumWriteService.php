<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ForumWriteService — مدیریت تالارها (DOC-094 §4: admin).
 */

namespace Modules\Forum\Application\UseCases;

use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Forum\Domain\Entities\Forum;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\ForumRepositoryInterface;
use Modules\Forum\Domain\Repositories\ThreadRepositoryInterface;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ForumWriteService — CRUD تالار با گارد حذف (تالار دارای موضوع حذف نمی‌شود).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ForumWriteService
{
    /**
     * @param array<string, mixed> $config کلید config('forum')
     */
    public function __construct(
        private readonly ForumRepositoryInterface $forums,
        private readonly ThreadRepositoryInterface $threads,
        private readonly array $config = [],
    ) {
    }

    /** @param array<string, mixed> $input */
    public function createForum(array $input): Forum
    {
        $validated = $this->validate($input);

        $slug = $this->resolveSlug((string) ($validated['slug'] ?? ''), (string) $validated['title']);
        $parentId = $this->resolveParentId($input['parent_id'] ?? null);

        $forum = Forum::create(
            title: \trim((string) $validated['title']),
            slug: $slug,
            parentId: $parentId,
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            position: (int) ($input['position'] ?? 0),
        );
        $this->forums->save($forum);
        $this->invalidateCache();

        return $forum;
    }

    /** @param array<string, mixed> $input */
    public function updateForum(Forum $forum, array $input): Forum
    {
        $validated = $this->validate($input);

        $slug = Slug::slugify((string) ($validated['slug'] ?? $forum->slug()));
        if ($slug !== '' && $slug !== $forum->slug()) {
            if ($this->forums->slugExists($slug, $forum->id())) {
                $slug = Slug::unique($slug, fn (string $c): bool => $this->forums->slugExists($c, $forum->id()))->toString();
            }
            $forum->revise(
                title: \trim((string) $validated['title']),
                slug: $slug,
                parentId: $this->resolveParentId($input['parent_id'] ?? null, $forum->id()),
                description: isset($validated['description']) ? (string) $validated['description'] : null,
                position: (int) ($input['position'] ?? $forum->position()),
            );
        } else {
            $forum->revise(
                title: \trim((string) $validated['title']),
                slug: null,
                parentId: $this->resolveParentId($input['parent_id'] ?? null, $forum->id()),
                description: isset($validated['description']) ? (string) $validated['description'] : null,
                position: (int) ($input['position'] ?? $forum->position()),
            );
        }

        $this->forums->save($forum);
        $this->invalidateCache();

        return $forum;
    }

    public function setLocked(Forum $forum, bool $locked): Forum
    {
        $forum->setLocked($locked);
        $this->forums->save($forum);
        $this->invalidateCache();

        return $forum;
    }

    /** حذف تالار — فقط وقتی موضوعی ندارد (گارد data-integrity). */
    public function deleteForum(Forum $forum): void
    {
        $id = (int) $forum->id();
        foreach ($this->forums->all() as $candidate) {
            if ($candidate->parentId() === $id) {
                throw ForumException::forumHasThreads();
            }
        }
        if ($this->threads->countForForum($id) > 0) {
            throw ForumException::forumHasThreads();
        }

        $this->forums->delete($id);
        $this->invalidateCache();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $input */
    private function validate(array $input): array
    {
        return Validator::validate($input, [
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer'],
        ], attributes: ['title' => 'عنوان تالار']);
    }

    private function resolveSlug(string $explicit, string $title): string
    {
        $base = $explicit !== '' ? Slug::slugify($explicit) : Slug::slugify($title);
        if ($base === '') {
            $base = 'forum';
        }

        return Slug::unique($base, fn (string $candidate): bool => $this->forums->slugExists($candidate))->toString();
    }

    private function resolveParentId(mixed $parentId, ?int $selfId = null): ?int
    {
        if ($parentId === null || $parentId === '' || $parentId === '0' || $parentId === 0) {
            return null;
        }

        $id = (int) $parentId;
        if ($selfId !== null && $id === $selfId) {
            return null;   // ارجاع به خود → ریشه
        }

        return $this->forums->findById($id) !== null ? $id : null;
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
