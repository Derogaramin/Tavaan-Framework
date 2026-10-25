<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateAdminController — مدیریت گواهی‌ها (/admin/lms/certificates).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Application\UseCases\CertificateService;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CertificateAdminController
{
    public function __construct(
        private readonly CertificateRepositoryInterface $certificates,
        private readonly CertificateService $service,
        private readonly CourseRepositoryInterface $courses,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('lms.certificate.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['q'] = $q;
        }

        $result = $this->certificates->paginate($filters, $page, 20);

        $rows = [];
        foreach ($result['rows'] as $certificate) {
            $rows[] = [
                'certificate' => $certificate,
                'course' => $this->courses->findById($certificate->courseId()),
                'student' => $this->users->displayNameOf($certificate->studentId(), 'دانشجو'),
            ];
        }

        return View::render('lms::admin.certificates-index', [
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
        ]);
    }

    public function revoke(string $id): mixed
    {
        $this->authorize('lms.certificate.revoke');

        $certificate = $this->certificates->findById($id);
        if ($certificate === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.certificate_not_found', context: ['id' => $id]);
        }

        $this->service->revoke($certificate);
        Session::flash('status', 'certificate_revoked');

        return back();
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
