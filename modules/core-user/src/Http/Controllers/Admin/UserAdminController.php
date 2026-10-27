<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول کاربران پایه
 * UserAdminController — مدیریت کاربران (/admin/users).
 */

namespace Modules\CoreUser\Http\Controllers\Admin;

use Modules\CoreUser\Application\UseCases\UserAdministrationService;
use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Domain\Enums\UserStatus;
use Modules\CoreUser\Domain\Repositories\UserRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;

/**
 * UserAdminController — CRUD کاربران با Gate (user.view/create/update/delete).
 *
 * @api 1.0
 * @see modules/core-user/README.md
 */
final class UserAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserAdministrationService $admin,
    ) {
    }

    /** GET /admin/users */
    public function index(RequestInterface $request): mixed
    {
        Gate::authorize('user.view');

        $page = \max(1, (int) $request->query('page', 1));
        $search = \trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $result = $this->admin->paginate($search, $status !== '' ? $status : null, $page);

        return view('core-user::admin.users-index', [
            'users' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => (int) config('core-user.admin.per_page', 20),
            'search' => $search,
            'status' => $status,
            'statuses' => UserStatus::cases(),
            'currentUserId' => (int) Auth::id(),
            'ok' => Session::pull('core_user_admin_ok'),
        ]);
    }

    /** GET /admin/users/create */
    public function create(): mixed
    {
        Gate::authorize('user.create');

        return view('core-user::admin.user-form', [
            'user' => null,
            'userRoles' => [],
            'availableRoles' => $this->admin->availableRoles(),
            'statuses' => UserStatus::cases(),
            'errors' => Session::pull('core_user_admin_errors') ?? [],
            'old' => Session::pull('core_user_admin_old') ?? [],
        ]);
    }

    /** POST /admin/users */
    public function store(RequestInterface $request): mixed
    {
        Gate::authorize('user.create');

        $input = $this->formInput($request, withPassword: true);

        try {
            $user = $this->admin->create($input, $this->rolesInput($request));
        } catch (ValidationException $e) {
            Session::flash('core_user_admin_errors', $e->errors);
            Session::flash('core_user_admin_old', $input);

            return redirect('/admin/users/create');
        }

        Session::flash('core_user_admin_ok', trans('core-user::messages.user_created'));

        return redirect('/admin/users/' . $user->id . '/edit');
    }

    /** GET /admin/users/{id}/edit */
    public function edit(string $id): mixed
    {
        Gate::authorize('user.update');
        $user = $this->findOr404($id);

        return view('core-user::admin.user-form', [
            'user' => $user,
            'userRoles' => $this->admin->rolesOf($user->id),
            'availableRoles' => $this->admin->availableRoles(),
            'statuses' => UserStatus::cases(),
            'errors' => Session::pull('core_user_admin_errors') ?? [],
            'old' => Session::pull('core_user_admin_old') ?? [],
            'ok' => Session::pull('core_user_admin_ok'),
        ]);
    }

    /** POST /admin/users/{id} */
    public function update(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('user.update');
        $user = $this->findOr404($id);
        $input = $this->formInput($request, withPassword: false);

        try {
            $this->admin->update($user, $input, $this->rolesInput($request), actorId: (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('core_user_admin_errors', $e->errors);
            Session::flash('core_user_admin_old', $input);

            return redirect('/admin/users/' . $user->id . '/edit');
        }

        Session::flash('core_user_admin_ok', trans('core-user::messages.user_updated'));

        return redirect('/admin/users/' . $user->id . '/edit');
    }

    /** POST /admin/users/{id}/status */
    public function setStatus(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('user.update');
        $user = $this->findOr404($id);

        $status = UserStatus::tryFrom((string) $request->input('status', ''));
        if ($status === null) {
            Session::flash('core_user_admin_errors', ['status' => [trans('core-user::messages.invalid_status')]]);

            return redirect('/admin/users');
        }

        try {
            $this->admin->setStatus($user, $status, actorId: (int) Auth::id());
            Session::flash('core_user_admin_ok', trans('core-user::messages.status_changed'));
        } catch (ValidationException $e) {
            Session::flash('core_user_admin_errors', $e->errors);
        }

        return redirect('/admin/users');
    }

    /** POST /admin/users/{id}/password — بازنشانی رمز */
    public function resetPassword(string $id, RequestInterface $request): mixed
    {
        Gate::authorize('user.update');
        $user = $this->findOr404($id);

        try {
            $this->admin->resetPassword($user, (string) $request->input('password', ''));
            Session::flash('core_user_admin_ok', trans('core-user::messages.password_reset'));
        } catch (ValidationException $e) {
            Session::flash('core_user_admin_errors', $e->errors);
        }

        return redirect('/admin/users/' . $user->id . '/edit');
    }

    /** DELETE /admin/users/{id} */
    public function destroy(string $id): mixed
    {
        Gate::authorize('user.delete');
        $user = $this->findOr404($id);

        try {
            $this->admin->delete($user, actorId: (int) Auth::id());
            Session::flash('core_user_admin_ok', trans('core-user::messages.user_deleted'));
        } catch (ValidationException $e) {
            Session::flash('core_user_admin_errors', $e->errors);
        }

        return redirect('/admin/users');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): User
    {
        if (\ctype_digit($id)) {
            $user = $this->users->findUserById((int) $id);
            if ($user !== null) {
                return $user;
            }
        }

        throw new RouteNotFoundException(messageId: 'core-user::messages.user_not_found', context: ['id' => $id]);
    }

    /** @return array<string, mixed> */
    private function formInput(RequestInterface $request, bool $withPassword): array
    {
        $input = [
            'name' => (string) $request->input('name', ''),
            'email' => (string) $request->input('email', ''),
            'mobile' => (string) $request->input('mobile', ''),
            'bio' => (string) $request->input('bio', ''),
            'status' => (string) $request->input('status', 'active'),
        ];

        if ($withPassword) {
            $input['password'] = (string) $request->input('password', '');
        }

        return $input;
    }

    /** @return list<string> */
    private function rolesInput(RequestInterface $request): array
    {
        $roles = $request->input('roles', []);
        if (!\is_array($roles)) {
            return [];
        }

        return \array_values(\array_filter(\array_map('strval', $roles), static fn (string $k): bool => $k !== ''));
    }
}
