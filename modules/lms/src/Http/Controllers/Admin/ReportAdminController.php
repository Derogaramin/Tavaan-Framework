<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * ReportAdminController — گزارش پیشرفت (/admin/lms/reports).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\Lms\Application\UseCases\ReportService;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * DOC-095 §6: گزارش پیشرفت — موتور report (CSV/HTML).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class ReportAdminController
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CourseRepositoryInterface $courses,
    ) {
    }

    /** گزارش کلی (admin: همهٔ دوره‌ها • مدرس: دوره‌های خود) + جزئیات یک دوره. */
    public function progress(RequestInterface $request): mixed
    {
        $this->authorize('lms.report.view');

        $instructorScope = Gate::allows('lms.course.view') ? null : (int) Auth::id();

        $courseId = (string) $request->input('course_id', '');
        $detail = null;
        if ($courseId !== '') {
            $course = $this->courses->findById($courseId);
            if ($course === null) {
                throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['id' => $courseId]);
            }
            if ($instructorScope !== null && $course->instructorId() !== $instructorScope) {
                throw new ForbiddenException(messageId: 'lms::messages.forbidden');
            }
            $detail = $this->reports->courseProgress($course);
        }

        return View::render('lms::admin.report', [
            'overview' => $this->reports->overview($instructorScope),
            'detail' => $detail,
            'courses' => $this->courses->paginate(
                $instructorScope !== null ? ['instructor_id' => $instructorScope] : [],
                1,
                100,
            )['rows'],
        ]);
    }

    /** CSV گزارش پیشرفت. */
    public function progressCsv(RequestInterface $request): mixed
    {
        $this->authorize('lms.report.view');

        $instructorScope = Gate::allows('lms.course.view') ? null : (int) Auth::id();
        $csv = $this->reports->toCsv($this->reports->overview($instructorScope));

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            $csv,
            200,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="lms-progress.csv"'],
        );
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
