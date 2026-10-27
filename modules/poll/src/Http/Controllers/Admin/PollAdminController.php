<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول نظرسنجی
 * PollAdminController — مدیریت نظرسنجی‌ها (/admin/polls).
 */

namespace Modules\Poll\Http\Controllers\Admin;

use Modules\Poll\Application\UseCases\PollWriteService;
use Modules\Poll\Domain\Entities\Poll;
use Modules\Poll\Domain\Repositories\PollRepositoryInterface;
use Modules\Poll\Domain\Repositories\VoteRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * PollAdminController — CRUD + باز/بستن رأی‌گیری با Gate (poll.*).
 *
 * @api 1.0
 * @see modules/poll/README.md
 */
final class PollAdminController
{
    public function __construct(
        private readonly PollRepositoryInterface $polls,
        private readonly VoteRepositoryInterface $votes,
        private readonly PollWriteService $write,
    ) {
    }

    /** GET /admin/polls */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('poll.view');

        $page = \max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $perPage = (int) config('poll.admin_per_page', 15);

        $filters = ['with_trashed' => false];
        if ($status !== '' && \in_array($status, ['draft', 'open', 'closed'], true)) {
            $filters['status'] = $status;
        }

        $result = $this->polls->paginate($filters, $page, $perPage);

        $voteCounts = [];
        foreach ($result['rows'] as $poll) {
            $voteCounts[$poll->id()] = $this->votes->countByPoll($poll->id());
        }

        return view('poll::admin.polls-index', [
            'polls' => $result['rows'],
            'voteCounts' => $voteCounts,
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'ok' => Session::pull('poll_admin_ok'),
        ]);
    }

    /** GET /admin/polls/create */
    public function create(): mixed
    {
        Gate::authorize('poll.create');

        return view('poll::admin.poll-form', [
            'poll' => null,
            'maxOptions' => (int) config('poll.max_options', 10),
            'errors' => Session::pull('poll_admin_errors') ?? [],
            'old' => Session::pull('poll_admin_old') ?? [],
        ]);
    }

    /** POST /admin/polls */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('poll.create');

        $input = $this->pollInput($request);

        try {
            $poll = $this->write->create($input, (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('poll_admin_errors', $e->errors);
            Session::flash('poll_admin_old', $input);

            return redirect('/admin/polls/create');
        }

        Session::flash('poll_admin_ok', trans('poll::messages.poll_created'));

        return redirect('/admin/polls/' . $poll->id() . '/edit');
    }

    /** GET /admin/polls/{id}/edit */
    public function edit(string $id): mixed
    {
        Gate::authorize('poll.update');
        $poll = $this->findOr404($id);

        return view('poll::admin.poll-form', [
            'poll' => $poll,
            'maxOptions' => (int) config('poll.max_options', 10),
            'voteCount' => $this->votes->countByPoll($poll->id()),
            'errors' => Session::pull('poll_admin_errors') ?? [],
            'old' => Session::pull('poll_admin_old') ?? [],
            'ok' => Session::pull('poll_admin_ok'),
        ]);
    }

    /** POST /admin/polls/{id} */
    public function update(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('poll.update');
        $poll = $this->findOr404($id);
        $input = $this->pollInput($request);

        try {
            $this->write->update($poll, $input);
        } catch (ValidationException $e) {
            Session::flash('poll_admin_errors', $e->errors);
            Session::flash('poll_admin_old', $input);

            return redirect('/admin/polls/' . $poll->id() . '/edit');
        }

        Session::flash('poll_admin_ok', trans('poll::messages.poll_updated'));

        return redirect('/admin/polls/' . $poll->id() . '/edit');
    }

    /** POST /admin/polls/{id}/open */
    public function open(string $id): mixed
    {
        Gate::authorize('poll.open');
        $poll = $this->findOr404($id);

        try {
            $this->write->open($poll);
            Session::flash('poll_admin_ok', trans('poll::messages.poll_opened'));
        } catch (\DomainException $e) {
            Session::flash('poll_admin_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/polls/' . $poll->id() . '/edit');
    }

    /** POST /admin/polls/{id}/close */
    public function close(string $id): mixed
    {
        Gate::authorize('poll.open');
        $poll = $this->findOr404($id);

        try {
            $this->write->close($poll);
            Session::flash('poll_admin_ok', trans('poll::messages.poll_closed'));
        } catch (\DomainException $e) {
            Session::flash('poll_admin_errors', ['status' => [$e->getMessage()]]);
        }

        return redirect('/admin/polls/' . $poll->id() . '/edit');
    }

    /** DELETE /admin/polls/{id} — حذف نرم */
    public function destroy(string $id): mixed
    {
        Gate::authorize('poll.delete');
        $poll = $this->findOr404($id, withTrashed: false);

        $this->write->trash($poll);
        Session::flash('poll_admin_ok', trans('poll::messages.poll_trashed'));

        return redirect('/admin/polls');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id, bool $withTrashed = true): Poll
    {
        $poll = $this->polls->findById($id);

        if ($poll === null || (!$withTrashed && $poll->isTrashed())) {
            throw new RouteNotFoundException(messageId: 'poll::messages.poll_not_found', context: ['id' => $id]);
        }

        return $poll;
    }

    /** @return array<string, mixed> */
    private function pollInput(RequestInterface $request): array
    {
        $options = $request->input('options', []);
        if (!\is_array($options)) {
            $options = [];
        }

        return [
            'question' => (string) $request->input('question', ''),
            'slug' => (string) $request->input('slug', ''),
            'options' => $options,
            'multiple' => (bool) $request->input('multiple', false),
            'starts_at' => (string) $request->input('starts_at', ''),
            'ends_at' => (string) $request->input('ends_at', ''),
            'status' => (string) $request->input('status', 'draft'),
        ];
    }
}
