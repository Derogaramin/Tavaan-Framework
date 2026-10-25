<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ActivityAdminController — فعالیت‌ها/کارها (/admin/crm/activities).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\ActivityService;
use Modules\Crm\Domain\Repositories\ActivityRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class ActivityAdminController
{
    public function __construct(
        private readonly ActivityService $service,
        private readonly ActivityRepositoryInterface $activities,
        private readonly UserReaderInterface $users,
    ) {
    }

    /** کارهای من (due/overdue) + تاریخچه. */
    public function index(RequestInterface $request): mixed
    {
        $this->authorize('crm.activity.manage');

        $me = (int) Auth::id();

        return View::render('crm::admin.activities-index', [
            'tasks' => $this->activities->dueFor($me),
            'recent' => $this->activities->forSubject(limit: 50),
            'userName' => $this->users->displayNameOf($me, 'کاربر'),
        ]);
    }

    /** ثبت فعالیت روی یک موجودیت (contact/lead/opportunity/ticket). */
    public function store(RequestInterface $request): mixed
    {
        $this->authorize('crm.activity.manage');

        try {
            $this->service->log($request->all(), (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'activity_logged');

        return back();
    }

    public function complete(string $id): mixed
    {
        $this->authorize('crm.activity.manage');

        $activity = $this->activities->findById($id);
        if ($activity === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.activity_not_found', context: ['id' => $id]);
        }
        if ($activity->userId() !== (int) Auth::id() && !Gate::allows('crm.contact.update')) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['ability' => 'complete']);
        }

        $this->service->complete($activity);
        Session::flash('status', 'activity_completed');

        return back();
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
