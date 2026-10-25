<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CatalogService — خواندن کاتالوگ عمومی دوره‌ها (DOC-095 §1.1: Course Catalog).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Search;

/**
 * CatalogService — فهرست/جزئیات/جست‌وجوی دوره‌های منتشرشده.
 *
 * جست‌وجو: ایندکس native موتور search (فارسی‌آگاه) با fallback روی
 * LIKE + واریانت رقم (الگوی انجمن) — تاب‌آوری در برابر ایندکس مانده.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CatalogService
{
    /**
     * @param array<string, mixed> $config کلید config('lms')
     */
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly UserReaderInterface $users,
        private readonly array $config = [],
    ) {
    }

    /**
     * فهرست دوره‌های منتشرشده.
     *
     * @param array{category_id?: int|null, level?: string, sort?: string} $filters
     *
     * @return array{rows: list<Course>, total: int, page: int, perPage: int}
     */
    public function listPublished(array $filters = [], int $page = 1): array
    {
        $perPage = $this->perPage();

        return $this->remember('lms.catalog.' . \substr(\hash('sha256', (string) \json_encode([$filters, $page], JSON_THROW_ON_ERROR)), 0, 24), function () use ($filters, $page, $perPage): array {
            $rows = $this->courses->paginate(['status' => 'published'] + $filters, $page, $perPage);
            foreach ($rows['rows'] as $course) {
                $rows['instructors'][$course->id()] = $this->instructorName($course->instructorId());
            }

            return $rows;
        });
    }

    /** جزئیات دورهٔ منتشرشده + ساختار بخش/درس + نام مدرس. */
    public function showPublished(string $slug): array
    {
        $course = $this->courses->findBySlug($slug);
        if ($course === null || !$course->isPublished()) {
            throw new RouteNotFoundException(messageId: 'lms::messages.course_not_found', context: ['slug' => $slug]);
        }

        return [
            'course' => $course,
            'structure' => $this->courses->structureOf($course->id()),
            'instructor' => $this->instructorName($course->instructorId()),
            'category' => $course->categoryId() !== null ? $this->courses->findCategory($course->categoryId()) : null,
            'related' => $this->courses->related($course),
            'prerequisites' => $this->prerequisiteCourses($course),
        ];
    }

    /**
     * جست‌وجوی فارسی ( DOC-095 §meta: search).
     *
     * @return array{rows: list<Course>, total: int, page: int, perPage: int, usedIndex: bool}
     */
    public function search(string $query, int $page = 1): array
    {
        $query = \trim($query);
        if ($query === '') {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'perPage' => $this->perPage(), 'usedIndex' => false];
        }

        // ۱) ایندکس native (فارسی‌آگاه)
        if ($this->searchEnabled()) {
            try {
                $ids = [];
                $result = Search::search($this->searchIndex(), $query, ['limit' => 50]);
                foreach ((array) (\method_exists($result, 'hits') ? $result->hits() : []) as $hit) {
                    $id = \is_array($hit) ? ($hit['id'] ?? null) : null;
                    if (\is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }

                if ($ids !== []) {
                    $courses = [];
                    foreach ($ids as $id) {
                        $course = $this->courses->findById($id);
                        if ($course !== null && $course->isPublished()) {
                            $courses[$course->id()] = $course;
                        }
                    }
                    if ($courses !== []) {
                        $rows = \array_values($courses);
                        $total = \count($rows);
                        $perPage = $this->perPage();
                        $offset = ($page - 1) * $perPage;

                        return [
                            'rows' => \array_slice($rows, $offset, $perPage),
                            'total' => $total,
                            'page' => $page,
                            'perPage' => $perPage,
                            'usedIndex' => true,
                        ];
                    }
                }
            } catch (\Throwable) {
                // fallback
            }
        }

        // ۲) fallback: LIKE با واریانت رقم فارسی/لاتین
        $result = $this->courses->searchPublished($query, $page, $this->perPage());

        return $result + ['usedIndex' => false];
    }

    public function instructorName(int $instructorId): string
    {
        return $this->users->displayNameOf($instructorId, 'مدرس');
    }

    /** @return list<Course> */
    public function prerequisiteCourses(Course $course): array
    {
        $list = [];
        foreach ($course->prerequisiteIds() as $pid) {
            $prereq = $this->courses->findById($pid);
            if ($prereq !== null) {
                $list[] = $prereq;
            }
        }

        return $list;
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function perPage(): int
    {
        return \max(1, (int) ($this->config['per_page'] ?? 12));
    }

    private function searchEnabled(): bool
    {
        return (bool) ($this->config['search']['enabled'] ?? true);
    }

    private function searchIndex(): string
    {
        return (string) ($this->config['search']['index'] ?? 'lms_courses');
    }

    /** کش با tag انجمن‌مانند (config lms.cache) — fallback بدون tag. */
    private function remember(string $key, \Closure $callback): mixed
    {
        $cache = (array) ($this->config['cache'] ?? []);
        if (!(bool) ($cache['enabled'] ?? true)) {
            return $callback();
        }

        $ttl = (int) ($cache['list_ttl'] ?? 60);

        try {
            return Cache::tags(['lms'])->remember($key, $ttl, $callback);
        } catch (\Throwable) {
            try {
                return Cache::remember($key, $ttl, $callback);
            } catch (\Throwable) {
                return $callback();
            }
        }
    }
}
