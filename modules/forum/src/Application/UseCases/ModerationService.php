<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ModerationService — صف گزارش‌ها: resolve/dismiss (DOC-094 §4/§5 + E2E-04).
 */

namespace Modules\Forum\Application\UseCases;

use Modules\Forum\Domain\Entities\Flag;
use Modules\Forum\Domain\Enums\FlagStatus;
use Modules\Forum\Domain\Repositories\FlagRepositoryInterface;
use Modules\Forum\Domain\Repositories\PostRepositoryInterface;

/**
 * ModerationService — اقدام روی صف: resolve (پنهان‌کردن پست) یا dismiss.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ModerationService
{
    /**
     * @param array<string, mixed> $config کلید config('forum.moderation')
     */
    public function __construct(
        private readonly FlagRepositoryInterface $flags,
        private readonly PostRepositoryInterface $posts,
        private readonly PostService $postService,
        private readonly array $config = [],
    ) {
    }

    /**
     * بررسی گزارش → پنهان‌کردن پست (hide_on_resolve) + resolve همهٔ
     * گزارش‌های باز همان پست.
     */
    public function resolve(Flag $flag, int $moderatorId, string $note = ''): void
    {
        if ((bool) ($this->config['hide_on_resolve'] ?? true)) {
            $post = $this->posts->findById($flag->postId());
            if ($post !== null && $post->isVisible()) {
                $this->postService->hide($post, $moderatorId, $note !== '' ? $note : 'flag:' . (string) $flag->id());
            }
        }

        $flag->resolve($moderatorId);
        $this->flags->save($flag);

        // بقیهٔ گزارش‌های باز همان پست هم خودکار resolve می‌شوند
        $queue = $this->flags->paginate(FlagStatus::Pending, 1, 100);
        foreach ($queue['rows'] as $pending) {
            if ($pending->postId() === $flag->postId() && $pending->id() !== $flag->id()) {
                $pending->resolve($moderatorId);
                $this->flags->save($pending);
            }
        }
    }

    public function dismiss(Flag $flag, int $moderatorId): void
    {
        $flag->dismiss($moderatorId);
        $this->flags->save($flag);
    }

    /**
     * صف pending — صفحه‌بندی‌شده.
     *
     * @return array{rows: list<Flag>, total: int, page: int, perPage: int}
     */
    public function pendingQueue(int $page = 1, int $perPage = 20): array
    {
        $result = $this->flags->paginate(FlagStatus::Pending, $page, $perPage);

        return [...$result, 'page' => \max(1, $page), 'perPage' => $perPage];
    }

    public function pendingCount(): int
    {
        return $this->flags->countByStatus(FlagStatus::Pending);
    }
}
