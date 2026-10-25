<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LmsController — کاتالوگ عمومی دوره‌ها (DOC-095 §1.1: Course Catalog).
 */

namespace Modules\Lms\Http\Controllers;

use Modules\Lms\Application\UseCases\CatalogService;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class LmsController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CourseRepositoryInterface $courses,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly \Modules\Lms\Domain\Repositories\CertificateRepositoryInterface $certificates,
    ) {
    }

    /** فهرست دوره‌ها — /lms */
    public function index(RequestInterface $request): mixed
    {
        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($sort = (string) $request->input('sort', '')) !== '') {
            $filters['sort'] = $sort;
        }
        if (($level = (string) $request->input('level', '')) !== '') {
            $filters['level'] = $level;
        }

        $result = $this->catalog->listPublished($filters, $page);

        return View::render('lms::front.index', [
            'courses' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'categories' => $this->courses->allCategories(),
            'instructors' => $result['instructors'] ?? [],
            'filters' => $filters,
        ]);
    }

    /** جزئیات دوره — /lms/courses/{slug} */
    public function show(RequestInterface $request, string $slug): mixed
    {
        $data = $this->catalog->showPublished($slug);
        /** @var \Modules\Lms\Domain\Entities\Course $course */
        $course = $data['course'];

        $enrollment = null;
        $certificate = null;
        if (Auth::check()) {
            $studentId = (int) Auth::id();
            $enrollment = $this->enrollments->find($studentId, $course->id());
            if ($enrollment !== null) {
                $certificate = $this->certificates->findForEnrollment($enrollment->id());
            }
        }

        return View::render('lms::front.course-show', $data + [
            'enrollment' => $enrollment,
            'certificate' => $certificate,
            'user' => Auth::user(),
        ]);
    }

    /** جست‌وجوی فارسی — /lms/search?q= */
    public function search(RequestInterface $request): mixed
    {
        $query = (string) $request->input('q', '');
        $page = \max(1, (int) $request->input('page', 1));

        $result = $this->catalog->search($query, $page);

        return View::render('lms::front.search', [
            'courses' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'query' => $query,
        ]);
    }

    /** دوره‌های یک دسته — /lms/category/{slug} */
    public function category(RequestInterface $request, string $slug): mixed
    {
        $category = $this->courses->findCategoryBySlug($slug);
        if ($category === null) {
            throw new RouteNotFoundException(messageId: 'lms::messages.category_not_found', context: ['slug' => $slug]);
        }

        $page = \max(1, (int) $request->input('page', 1));
        $result = $this->catalog->listPublished(['category_id' => $category->id()], $page);

        return View::render('lms::front.index', [
            'courses' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'categories' => $this->courses->allCategories(),
            'instructors' => $result['instructors'] ?? [],
            'filters' => ['category_id' => $category->id()],
            'category' => $category,
        ]);
    }
}
