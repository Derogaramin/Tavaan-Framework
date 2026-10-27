<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظر
 * CommentController — ثبت و دریافت رشتهٔ نظرات (عمومی، پلی‌مورفیک).
 */

namespace Modules\Comment\Http\Controllers;

use Modules\Comment\Application\CommentableRegistry;
use Modules\Comment\Application\UseCases\SubmitCommentService;
use Modules\Comment\Domain\Enums\CommentStatus;
use Modules\Comment\Domain\ValueObjects\ThreadNode;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * CommentController — endpointهای عمومی نظرات standalone:
 *
 * - POST /comments/{type}/{id}  → ثبت نظر (فرم وب یا fetch)
 * - GET  /comments/{type}/{id}  → رشتهٔ تأییدشده (HTML partial یا JSON)
 *
 * 🔒 type از allowlist رجیستری عبور می‌کند؛ پاسخ JSON با ساختار پایدار.
 *
 * @api 1.0
 * @see modules/comment/README.md
 */
final class CommentController
{
    public function __construct(
        private readonly SubmitCommentService $submit,
        private readonly CommentableRegistry $registry,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /comments/{type}/{id} */
    public function show(string $type, string $id, RequestInterface $request): mixed
    {
        if (!$this->registry->has($type)) {
            throw new ValidationException(
                errors: ['commentable_type' => ['نوع موضوع نظرپذیر ثبت نشده است.']],
                messageId: 'comment::messages.type_not_registered',
            );
        }

        $nodes = $this->submit->approvedThread($type, $id);

        if ($request->expectsJson()) {
            return $this->json([
                'data' => \array_map(static fn (ThreadNode $node): array => self::nodeToArray($node), $nodes),
                'meta' => ['type' => $type, 'subject' => $id, 'count' => self::countNodes($nodes)],
            ]);
        }

        return view('comment::thread', [
            'type' => $type,
            'subjectId' => $id,
            'nodes' => $nodes,
            'errors' => Session::pull('comment_errors') ?? [],
            'old' => Session::pull('comment_old') ?? [],
            'ok' => Session::pull('comment_ok'),
            'guestAllowed' => $this->registry->guestAllowed($type),
            'canComment' => $this->registry->isOpen($type) && ($this->registry->guestAllowed($type) || Auth::check()),
        ]);
    }

    /** POST /comments/{type}/{id} */
    public function store(string $type, string $id, RequestInterface $request): mixed
    {
        $userId = Auth::id();

        $input = [
            'author_name' => (string) $request->input('author_name', ''),
            'author_email' => (string) $request->input('author_email', ''),
            'body' => (string) $request->input('body', ''),
            'parent_id' => (string) $request->input('parent_id', ''),
        ];

        try {
            $comment = $this->submit->submit($type, $id, $input, $userId, $request->ip());
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return $this->json(['errors' => $e->errors], 422);
            }

            Session::flash('comment_errors', $e->errors);
            Session::flash('comment_old', $input);

            return redirect($this->backUrl($request, $type, $id));
        }

        if ($request->expectsJson()) {
            return $this->json([
                'data' => [
                    'id' => $comment->id,
                    'status' => $comment->status->value,
                    'message' => $comment->status === CommentStatus::Approved
                        ? trans('comment::messages.posted_visible')
                        : trans('comment::messages.posted_pending'),
                ],
            ], 201);
        }

        Session::flash('comment_ok', $comment->status === CommentStatus::Approved
            ? trans('comment::messages.posted_visible')
            : trans('comment::messages.posted_pending'));

        return redirect($this->backUrl($request, $type, $id));
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** پاسخ JSON از binding هسته `response.factory` (بدون ارجاع به موتور — ARCH-003). */
    private function json(mixed $data, int $status = 200): mixed
    {
        return $this->container->make('response.factory')->json($data, $status);
    }

    /** مسیر بازگشت: referer امن (همان‌میزبان) یا URL ثبت‌شدهٔ موضوع. */
    private function backUrl(RequestInterface $request, string $type, string $id): string
    {
        $referer = $request->header('referer');
        if (\is_string($referer) && $referer !== '') {
            $host = \parse_url($referer, PHP_URL_HOST);
            $selfHost = \parse_url((string) config('app.url', ''), PHP_URL_HOST);
            if (\is_string($host) && ($selfHost === null || $host === $selfHost || $host === 'localhost' || $host === '127.0.0.1')) {
                return $referer;
            }
        }

        $url = $this->registry->url($type, $id);

        return $url !== '' ? $url : '/comments/' . \rawurlencode($type) . '/' . \rawurlencode($id);
    }

    /** @return array<string, mixed> */
    private static function nodeToArray(ThreadNode $node): array
    {
        $comment = $node->comment;

        return [
            'id' => $comment->id,
            'author_name' => $comment->authorName,
            'body' => $comment->body,
            'created_at' => $comment->createdAt?->format(\DateTimeInterface::ATOM),
            'parent_id' => $comment->parentId,
            'children' => \array_map(static fn (ThreadNode $child): array => self::nodeToArray($child), $node->children),
        ];
    }

    /** @param list<ThreadNode> $nodes */
    private static function countNodes(array $nodes): int
    {
        $total = 0;
        foreach ($nodes as $node) {
            $total += $node->count();
        }

        return $total;
    }
}
