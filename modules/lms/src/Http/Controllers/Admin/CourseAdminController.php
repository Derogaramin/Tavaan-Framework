<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseAdminController — مدیریت دوره/بخش/درس/دسته (/admin/lms/courses).
 */

namespace Modules\Lms\Http\Controllers\Admin;

use Modules\Lms\Application\UseCases\CourseAdminService;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * 🔒 مدرس دورهٔ خود را می‌بیند/می‌سازد؛ lms-admin همه را (CoursePolicy +
 * مجوزهای «همه» — DOC-095 §4).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CourseAdminController
{
    public function __construct(
        private readonly CourseAdminService $service,
        private readonly CourseRepositoryInterface $courses,
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('lms.course.view', own: 'lms.course.create');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }
        if (($q = (string) $request->input('q', '')) !== '') {
            $filters['search'] = $q;
        }

        // مدرس → فقط دوره‌های خودش؛ دارندگان lms.course.view → همه
        if (!Gate::allows('lms.course.view')) {
            $filters['instructor_id'] = (int) Auth::id();
        }

        $result = $this->courses->paginate($filters, $page, 20);

        return View::render('lms::admin.courses-index', [
            'courses' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'query' => (string) $request->input('q', ''),
            'statusFilter' => (string) $request->input('status', ''),
            'categories' => $this->courses->allCategories(),
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('lms.course.create');

        return View::render('lms::admin.course-form', [
            'course' => null,
            'structure' => [],
            'categories' => $this->courses->allCategories(),
            'quizzes' => [],
            'allCourses' => $this->selectableCourses(),
        ]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('lms.course.create');

        try {
            $course = $this->service->create($this->courseInput($request), (int) Auth::id());
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'course_created');

        return redirect('/admin/lms/courses/' . $course->id() . '/edit');
    }

    public function edit(string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        return View::render('lms::admin.course-form', [
            'course' => $course,
            'structure' => $this->courses->structureOf($course->id()),
            'categories' => $this->courses->allCategories(),
            'quizzes' => [],
            'allCourses' => $this->selectableCourses($course->id()),
            'enrollmentCount' => $this->enrollments->countByCourseAndStatus($course->id()),
        ]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        try {
            $course = $this->service->update($course, $this->courseInput($request));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'course_updated');

        return redirect('/admin/lms/courses/' . $course->id() . '/edit');
    }

    public function publish(string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('publish', $course);

        try {
            $this->service->publish($course);
        } catch (ValidationException|\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'course_published');

        return redirect('/admin/lms/courses/' . $course->id() . '/edit');
    }

    public function archive(string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        try {
            $this->service->archive($course);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'course_archived');

        return redirect('/admin/lms/courses/' . $course->id() . '/edit');
    }

    public function destroy(string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('delete', $course);

        $this->service->delete($course);
        Session::flash('status', 'course_deleted');

        return redirect('/admin/lms/courses');
    }

    // ─── بخش‌ها ───────────────────────────────────────────────────────────

    public function addSection(RequestInterface $request, string $id): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        try {
            $this->service->addSection($course, (string) $request->input('title', ''), $request->input('position') === null ? null : (int) $request->input('position'));
        } catch (ValidationException|\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'section_added');

        return back();
    }

    public function updateSection(RequestInterface $request, string $id, string $sectionId): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        $section = $this->sectionOf($course->id(), $sectionId);

        try {
            $this->service->updateSection($section, (string) $request->input('title', ''), $request->input('position') === null ? null : (int) $request->input('position'));
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'section_updated');

        return back();
    }

    public function deleteSection(string $id, string $sectionId): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        $this->service->deleteSection($this->sectionOf($course->id(), $sectionId));
        Session::flash('status', 'section_deleted');

        return back();
    }

    // ─── درس‌ها ───────────────────────────────────────────────────────────

    public function addLesson(RequestInterface $request, string $id, string $sectionId): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        $section = $this->sectionOf($course->id(), $sectionId);

        try {
            $this->service->addLesson($course, $section, $this->lessonInput($request));
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lesson_added');

        return back();
    }

    public function updateLesson(RequestInterface $request, string $id, string $lessonId): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        $lesson = $this->courses->findLesson($lessonId);
        if ($lesson === null || $lesson->courseId() !== $course->id()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.lesson_not_found');
        }

        try {
            $this->service->updateLesson($lesson, $this->lessonInput($request));
        } catch (ValidationException|\DomainException $e) {
            Session::flash('errors', $e instanceof ValidationException ? $e->errors : []);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'lesson_updated');

        return back();
    }

    public function deleteLesson(string $id, string $lessonId): mixed
    {
        $course = $this->findOr404($id);
        $this->authorizeModel('update', $course);

        $lesson = $this->courses->findLesson($lessonId);
        if ($lesson === null || $lesson->courseId() !== $course->id()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.lesson_not_found');
        }

        $this->service->deleteLesson($lesson);
        Session::flash('status', 'lesson_deleted');

        return back();
    }

    // ─── دسته‌ها ──────────────────────────────────────────────────────────

    public function storeCategory(RequestInterface $request): mixed
    {
        $this->authorize('lms.category.manage');

        try {
            $this->service->createCategory(
                (string) $request->input('title', ''),
                $request->input('description') === null ? null : (string) $request->input('description'),
            );
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'category_created');

        return back();
    }

    public function destroyCategory(string $categoryId): mixed
    {
        $this->authorize('lms.category.manage');

        $this->service->deleteCategory((int) $categoryId);
        Session::flash('status', 'category_deleted');

        return back();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function courseInput(RequestInterface $request): array
    {
        $prereqs = $request->input('prerequisites');

        return [
            'title' => (string) $request->input('title', ''),
            'slug' => (string) $request->input('slug', ''),
            'summary' => (string) $request->input('summary', ''),
            'description' => (string) $request->input('description', ''),
            'category_id' => $request->input('category_id') === null || $request->input('category_id') === '' ? null : (int) $request->input('category_id'),
            'level' => (string) $request->input('level', 'beginner'),
            'language' => (string) $request->input('language', 'fa'),
            'price' => (int) $request->input('price', 0),
            'is_free' => $request->input('is_free') !== null && $request->input('is_free') !== '0',
            'thumbnail' => (string) $request->input('thumbnail', ''),
            'prerequisites' => \is_array($prereqs) ? $prereqs : (string) ($prereqs ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function lessonInput(RequestInterface $request): array
    {
        return [
            'title' => (string) $request->input('title', ''),
            'slug' => (string) $request->input('slug', ''),
            'type' => (string) $request->input('type', 'text'),
            'content' => (string) $request->input('content', ''),
            'video_url' => (string) $request->input('video_url', ''),
            'attachment_path' => (string) $request->input('attachment_path', ''),
            'quiz_id' => (string) $request->input('quiz_id', ''),
            'duration' => (int) $request->input('duration', 0),
            'is_free_preview' => $request->input('is_free_preview') !== null && $request->input('is_free_preview') !== '0',
            'position' => $request->input('position') === null || $request->input('position') === '' ? null : (int) $request->input('position'),
        ];
    }

    private function findOr404(string $id): Course
    {
        $course = $this->courses->findById($id);
        if ($course === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['id' => $id]);
        }

        return $course;
    }

    private function sectionOf(string $courseId, string $sectionId): \Modules\Lms\Domain\Entities\Section
    {
        foreach ($this->courses->structureOf($courseId) as $branch) {
            if ($branch['section']->id() === $sectionId) {
                return $branch['section'];
            }
        }

        throw new RouteNotFoundException(messageId: 'lms::messages.section_not_found', context: ['id' => $sectionId]);
    }

    /** @return list<Course> فهرست دوره‌های منتشرشده برای انتخاب پیش‌نیاز */
    private function selectableCourses(?string $exceptId = null): array
    {
        $result = $this->courses->paginate(['status' => 'published'], 1, 100);
        $list = [];
        foreach ($result['rows'] as $course) {
            if ($course->id() !== $exceptId) {
                $list[] = $course;
            }
        }

        return $list;
    }

    private function authorize(string $permission, ?string $own = null): void
    {
        if (Gate::allows($permission)) {
            return;
        }
        if ($own !== null && Gate::allows($own)) {
            return;   // مدرس — دسترسی به فهرست/ساخت دورهٔ خود
        }

        throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['permission' => $permission]);
    }

    private function authorizeModel(string $ability, object $model): void
    {
        if (!Gate::allows($ability, $model)) {
            throw new ForbiddenException(messageId: 'lms::messages.forbidden', context: ['ability' => $ability]);
        }
    }
}
