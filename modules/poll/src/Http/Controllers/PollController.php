<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollController — نمایش عمومی و رأی‌گیری (/polls).
 */

namespace Modules\Poll\Http\Controllers;

use Modules\Poll\Application\UseCases\PollReadService;
use Modules\Poll\Application\UseCases\VoteService;
use Modules\Poll\Domain\Entities\Poll;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PollController — GET /polls + GET /polls/{slug} + POST /polls/{slug}/vote.
 *
 * 🔒 فقط نظرسنجی‌های public (open/closed) و غیرtrash نمایش داده می‌شوند.
 * پاسخ JSON با ساختار پایدار (data/meta) هم‌راستا با ماژول comment.
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollController
{
    public function __construct(
        private readonly PollReadService $read,
        private readonly VoteService $casting,
        private readonly ContainerInterface $container,
    ) {
    }

    /** GET /polls — فهرست نظرسنجی‌های باز */
    public function index(RequestInterface $request): mixed
    {
        $limit = (int) config('poll.per_page', 10);

        return view('poll::polls-index', [
            'polls' => $this->read->listOpen($limit),
        ]);
    }

    /** GET /polls/{slug} — فرم رأی یا نتایج */
    public function show(string $slug, RequestInterface $request): mixed
    {
        $poll = $this->findPublicOr404($slug);

        [$userId, $ip] = $this->voter($request);
        $hasVoted = $this->casting->hasVoted($poll, $userId, $ip);
        $showResults = $this->read->shouldShowResults($poll, $hasVoted);

        return view('poll::poll-show', [
            'poll' => $poll,
            'results' => $showResults ? $this->read->results($poll) : null,
            'hasVoted' => $hasVoted,
            'canVote' => $poll->isVotable() && !$hasVoted,
            'ok' => Session::pull('poll_vote_ok'),
            'errors' => Session::pull('poll_vote_errors') ?? [],
        ]);
    }

    /** POST /polls/{slug}/vote — ثبت رأی (فرم وب یا JSON) */
    public function vote(string $slug, RequestInterface $request): mixed
    {
        $poll = $this->findPublicOr404($slug);

        [$userId, $ip] = $this->voter($request);
        $indexes = $this->selectedIndexes($request);

        try {
            $votes = $this->casting->cast($poll, $indexes, $userId, $ip);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return $this->json(['errors' => $e->errors], 422);
            }

            Session::flash('poll_vote_errors', $e->errors);

            return redirect('/polls/' . $poll->slug());
        }

        if ($request->expectsJson()) {
            return $this->json([
                'data' => [
                    'votes' => \count($votes),
                    'message' => trans('poll::messages.vote_ok'),
                    'results' => $this->read->results($poll),
                ],
            ], 201);
        }

        Session::flash('poll_vote_ok', trans('poll::messages.vote_ok'));

        return redirect('/polls/' . $poll->slug());
    }

    /** GET /api/polls/{slug} — JSON (متادیتا + نتایج طبق قواعد نمایش) */
    public function apiShow(string $slug, RequestInterface $request): mixed
    {
        $poll = $this->findPublicOr404($slug);

        [$userId, $ip] = $this->voter($request);
        $hasVoted = $this->casting->hasVoted($poll, $userId, $ip);
        $showResults = $this->read->shouldShowResults($poll, $hasVoted);

        return $this->json([
            'data' => [
                'id' => $poll->id(),
                'slug' => $poll->slug(),
                'question' => $poll->question(),
                'options' => $poll->options(),
                'multiple' => $poll->multiple(),
                'status' => $poll->status()->value,
                'starts_at' => $poll->startsAt()?->format(\DateTimeInterface::ATOM),
                'ends_at' => $poll->endsAt()?->format(\DateTimeInterface::ATOM),
                'votable' => $poll->isVotable(),
                'has_voted' => $hasVoted,
                'results' => $showResults ? $this->read->results($poll) : null,
            ],
            'meta' => ['slug' => $slug],
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findPublicOr404(string $slug): Poll
    {
        $poll = $this->read->publicBySlug($slug);
        if ($poll === null) {
            throw new RouteNotFoundException(messageId: 'poll::messages.poll_not_found', context: ['slug' => $slug]);
        }

        return $poll;
    }

    /** @return array{0: ?int, 1: ?string} [userId, ip] */
    private function voter(RequestInterface $request): array
    {
        $raw = Auth::id();
        $userId = $raw === null ? null : (int) $raw;

        return [$userId, $request->ip()];
    }

    /**
     * گزینه‌های انتخاب‌شده از فرم/JSON: `options[]` (چندگانه) یا `option` (تک).
     *
     * @return list<int|string>
     */
    private function selectedIndexes(RequestInterface $request): array
    {
        $raw = $request->input('options');
        if (\is_array($raw)) {
            return \array_values($raw);
        }

        $single = $request->input('option');
        if ($single !== null && $single !== '') {
            return [\is_scalar($single) ? $single : ''];
        }

        return [];
    }

    /** پاسخ JSON از binding هسته `response.factory` (بدون ارجاع به موتور — ARCH-003). */
    private function json(mixed $data, int $status = 200): mixed
    {
        return $this->container->make('response.factory')->json($data, $status);
    }
}
