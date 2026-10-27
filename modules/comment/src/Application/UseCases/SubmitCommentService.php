<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * SubmitCommentService — ثبت نظر جدید (اعتبارسنجی + moderation + رویداد).
 */

namespace Modules\Comment\Application\UseCases;

use Modules\Comment\Application\CommentableRegistry;
use Modules\Comment\Contracts\CommentStoreInterface;
use Modules\Comment\Domain\Entities\Comment;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\Services\CommentModerator;
use Modules\Comment\Domain\Services\CommentThreadBuilder;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * SubmitCommentService — UseCase ثبت نظر عمومی (standalone).
 *
 * جریان: allowlist نوع → اعتبارسنجی ورودی → بررسی والد (هم‌subject + عمق) →
 * ساخت Comment → تصمیم moderation (اسپم/pending/تأیید خودکار) → ذخیره →
 * رویداد `comment.submitted` (+ `comment.status.changed` وقتی خودکار تأیید شود).
 *
 * 🔒 حریم خصوصی: IP خام ذخیره نمی‌شود — فقط hash(IP + app.key).
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class SubmitCommentService
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly CommentStoreInterface $store,
        private readonly CommentableRegistry $registry,
        private readonly CommentModerator $moderator,
        private readonly CommentThreadBuilder $threadBuilder,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input author_name/author_email/body/parent_id
     * @param int|string|null      $userId شناسهٔ کاربر واردشده (null = مهمان)
     * @param string|null          $ip     برای hash ضداسپم (اختیاری)
     *
     * @throws ValidationException نوع ثبت‌نشده/بسته، ورودی نامعتبر، والد نامعتبر
     */
    public function submit(string $type, string $subjectId, array $input, int|string|null $userId = null, ?string $ip = null): Comment
    {
        if (!$this->registry->has($type)) {
            throw new ValidationException(
                errors: ['commentable_type' => ['نوع موضوع نظرپذیر ثبت نشده است.']],
                messageId: 'comment::messages.type_not_registered',
            );
        }

        if (!$this->registry->isOpen($type)) {
            throw new ValidationException(
                errors: ['commentable_type' => ['ثبت نظر برای این موضوع بسته است.']],
                messageId: 'comment::messages.comments_closed',
            );
        }

        $guestAllowed = $this->registry->guestAllowed($type) && (bool) ($this->config['guest_allowed'] ?? true);
        if ($userId === null && !$guestAllowed) {
            throw new ValidationException(
                errors: ['author_name' => ['برای ثبت نظر باید وارد حساب کاربری شوید.']],
                messageId: 'comment::messages.login_required',
            );
        }

        $subjectId = \trim($subjectId);
        if ($subjectId === '' || \mb_strlen($subjectId) > 64) {
            throw new ValidationException(
                errors: ['commentable_id' => ['شناسهٔ موضوع نامعتبر است.']],
                messageId: 'comment::messages.invalid_subject',
            );
        }

        $validated = Validator::validate($input, [
            'author_name' => [$userId === null ? 'required' : 'nullable', 'string', 'min:3', 'max:190'],
            'author_email' => [$userId === null ? 'required' : 'nullable', 'email', 'max:190'],
            'body' => ['required', 'string', 'min:' . (int) ($this->config['min_length'] ?? 5), 'max:' . (int) ($this->config['max_length'] ?? 5000)],
            'parent_id' => ['nullable', 'string', 'size:26'],
        ]);

        $parentId = isset($validated['parent_id']) && \is_string($validated['parent_id']) && $validated['parent_id'] !== ''
            ? $validated['parent_id']
            : null;

        if ($parentId !== null) {
            $this->assertValidParent($type, $subjectId, $parentId);
        }

        $authorName = \trim((string) ($validated['author_name'] ?? ''));
        $authorEmail = \mb_strtolower(\trim((string) ($validated['author_email'] ?? '')));

        // کاربر واردشده بدون نام/ایمیل در فرم → از پروفایل (snapshot) پر می‌شود
        if ($userId !== null && ($authorName === '' || $authorEmail === '')) {
            [$authorName, $authorEmail] = $this->identityOf($userId, $authorName, $authorEmail);
        }

        $comment = Comment::submit(
            commentableType: $type,
            commentableId: $subjectId,
            authorName: $authorName,
            authorEmail: $authorEmail,
            body: (string) $validated['body'],
            status: CommentStatus::Pending,
            parentId: $parentId,
            userId: $userId !== null ? (string) $userId : null,
        );

        $status = $this->moderator->decide(
            $comment,
            authorHasApprovedHistory: $this->store->authorHasApprovedHistory($authorEmail),
        );
        $comment = $comment->withStatus($status);

        $this->store->save($comment);

        Event::dispatch('comment.submitted', [
            'comment_id' => $comment->id,
            'commentable_type' => $type,
            'commentable_id' => $subjectId,
            'status' => $status->value,
            'user_id' => $userId !== null ? (string) $userId : null,
            'ip_hash' => $this->hashIp($ip),
        ]);

        if ($status === CommentStatus::Approved) {
            Event::dispatch('comment.status.changed', [
                'comment_id' => $comment->id,
                'commentable_type' => $type,
                'commentable_id' => $subjectId,
                'status' => CommentStatus::Approved->value,
                'previous_status' => CommentStatus::Pending->value,
            ]);
        }

        return $comment;
    }

    /**
     * رشتهٔ نظرات تأییدشدهٔ یک subject (برای embed در ویوها).
     *
     * @return list<\Modules\Comment\Domain\ValueObjects\ThreadNode>
     */
    public function approvedThread(string $type, string $subjectId): array
    {
        $comments = $this->store->forSubject($type, $subjectId, CommentStatus::Approved);

        return $this->threadBuilder->build($comments);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function assertValidParent(string $type, string $subjectId, string $parentId): void
    {
        $parent = $this->store->findById($parentId);

        if ($parent === null || $parent->commentableType !== $type || $parent->commentableId !== $subjectId) {
            throw new ValidationException(
                errors: ['parent_id' => ['نظر والد معتبر نیست.']],
                messageId: 'comment::messages.invalid_parent',
            );
        }

        if (!$parent->status->isVisible()) {
            throw new ValidationException(
                errors: ['parent_id' => ['نمی‌توان به نظر تأییدنشده پاسخ داد.']],
                messageId: 'comment::messages.parent_not_visible',
            );
        }

        // عمق رشته: والد نباید در عمق ماکزیمم باشد
        $maxDepth = (int) ($this->config['thread']['max_depth'] ?? 8);
        $depth = 0;
        $cursor = $parent;
        while ($cursor->parentId !== null && $depth <= $maxDepth) {
            $cursor = $this->store->findById($cursor->parentId);
            if ($cursor === null) {
                break;
            }
            $depth++;
        }

        if ($depth >= $maxDepth) {
            throw new ValidationException(
                errors: ['parent_id' => ['حداکثر عمق پاسخ‌ها پر شده است.']],
                messageId: 'comment::messages.max_depth_reached',
            );
        }
    }

    /** @return array{0: string, 1: string} */
    private function identityOf(int|string $userId, string $name, string $email): array
    {
        // نام/ایمیل از پروفایل کاربر (پورت users.reader ماژول core-user)
        try {
            $container = \function_exists('app') ? \app() : null;
            if ($container !== null && $container->bound('users.reader')) {
                $snapshot = $container->make('users.reader')->findById($userId);
                if ($snapshot !== null) {
                    $name = $name !== '' ? $name : (string) $snapshot->name;
                    $email = $email !== '' ? $email : (string) $snapshot->email;
                }
            }
        } catch (\Throwable) {
            // بدون پروفایل — همان ورودی فرم
        }

        return [$name, $email];
    }

    /** 🔒 hash(IP + app.key) — بدون ذخیرهٔ IP خام. */
    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $salt = \function_exists('config') ? (string) config('app.key', '') : '';

        return \hash('sha256', $ip . '|' . $salt);
    }
}
