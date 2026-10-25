<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * EnrollmentAdminController — مدیریت ثبت‌نام‌ها (/admin/lms/enrollments).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Application\UseCases\CertificateService;
use Modules\Lms\Application\UseCases\EnrollmentService;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class EnrollmentAdminController
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly CourseRepositoryInterface $courses,
        private readonly EnrollmentService $service,
        private readonly CertificateService $certificates,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('lms.enrollment.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }
        if (($courseId = (string) $request->input('course_id', '')) !== '') {
            $filters['course_id'] = $courseId;
        }

        $result = $this->enrollments->paginate($filters, $page, 20);

        $rows = [];
        foreach ($result['rows'] as $enrollment) {
            $course = $this->courses->findById($enrollment->courseId());
            $rows[] = [
                'enrollment' => $enrollment,
                'course' => $course,
                'student' => $this->users->displayNameOf($enrollment->studentId(), 'دانشجو'),
            ];
        }

        return View::render('lms::admin.enrollments-index', [
            'rows' => $rows,
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => (string) $request->input('status', ''),
            'courses' => $this->courses->paginate([], 1, 100)['rows'],
        ]);
    }

    public function drop(string $id): mixed
    {
        $this->authorize('lms.enrollment.manage');

        $enrollment = $this->findOr404($id);
        try {
            $this->service->drop($enrollment);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'enrollment_dropped');

        return back();
    }

    public function reactivate(string $id): mixed
    {
        $this->authorize('lms.enrollment.manage');

        $enrollment = $this->findOr404($id);
        try {
            $enrollment->reactivate();
            $this->enrollments->save($enrollment);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'enrollment_reactivated');

        return back();
    }

    /** صدور دستی گواهی (force — مثلاً پس از بررسی پرونده). */
    public function issueCertificate(string $id): mixed
    {
        $this->authorize('lms.certificate.view');

        $enrollment = $this->findOr404($id);

        try {
            $certificate = $this->certificates->issueForEnrollment($enrollment, force: true);
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash($certificate !== null ? 'status' : 'error', $certificate !== null ? 'certificate_issued' : 'certificate_issue_failed');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function findOr404(string $id): \Modules\Lms\Domain\Entities\Enrollment
    {
        $enrollment = $this->enrollments->findById($id);
        if ($enrollment === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.enrollment_not_found', context: ['id' => $id]);
        }

        return $enrollment;
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
