<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CourseAdminService — نوشتن دوره/بخش/درس (DOC-095 §4: instructor).
 */

namespace Modules\Lms\Application\UseCases;

use Modules\Content\Domain\Services\Markdown;
use Modules\Content\Domain\ValueObjects\Slug;
use Modules\Lms\Domain\Entities\Course;
use Modules\Lms\Domain\Entities\Lesson;
use Modules\Lms\Domain\Entities\LmsCategory;
use Modules\Lms\Domain\Entities\Section;
use Modules\Lms\Domain\Enums\CourseLevel;
use Modules\Lms\Domain\Enums\LessonType;
use Modules\Lms\Domain\Repositories\CourseRepositoryInterface;
use Modules\Lms\Domain\Services\PrerequisiteChecker;
use Modules\Lms\Domain\ValueObjects\Money;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Cache;
use Tavaan\Core\Facade\Facades\Event;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * CourseAdminService — CRUD دوره + ساختار (بخش/درس) + دسته + پیش‌نیاز.
 *
 * 🔒 بدنهٔ درس/توضیح دوره: Markdown escape-first (ماژول content) +
 * `Security::sanitize(rich_text)` لایهٔ دوم (AC-BLOG-03-مشابه — ۰ XSS).
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class CourseAdminService
{
    /**
     * @param array<string, mixed> $config کلید config('lms')
     */
    public function __construct(
        private readonly CourseRepositoryInterface $courses,
        private readonly Markdown $markdown,
        private readonly PrerequisiteChecker $prerequisites,
        private readonly array $config = [],
    ) {
    }

    // ─── دوره ─────────────────────────────────────────────────────────────

    /**
     * ساخت دورهٔ پیش‌نویس.
     *
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $instructorId): Course
    {
        $validated = $this->validateCourse($input);
        $currency = (string) ($this->config['currency'] ?? 'IRR');

        $course = Course::draft(
            slug: $this->uniqueSlug((string) $validated['title'], (string) ($validated['slug'] ?? '')),
            title: (string) $validated['title'],
            instructorId: $instructorId,
            summary: $this->renderBody((string) ($validated['summary'] ?? '')) ?: null,
            description: $this->renderBody((string) ($validated['description'] ?? '')) ?: null,
            categoryId: ($validated['category_id'] ?? null) === null ? null : (int) $validated['category_id'],
            level: CourseLevel::tryFromString((string) ($validated['level'] ?? 'beginner')),
            language: (string) ($validated['language'] ?? 'fa'),
            price: $this->priceFrom($validated, $currency),
            thumbnail: ($validated['thumbnail'] ?? null) === null || $validated['thumbnail'] === '' ? null : (string) $validated['thumbnail'],
        );

        $this->courses->save($course);

        $prereqs = $this->normalizePrerequisites($validated['prerequisites'] ?? null, $course->id());
        if ($prereqs !== []) {
            $this->courses->replacePrerequisites($course->id(), $prereqs);
            $course = $this->courses->findById($course->id()) ?? $course;
        }

        $this->invalidateCache();

        return $course;
    }

    /**
     * ویرایش دوره.
     *
     * @param array<string, mixed> $input
     */
    public function update(Course $course, array $input): Course
    {
        $validated = $this->validateCourse($input);
        $currency = (string) ($this->config['currency'] ?? 'IRR');

        $slug = (string) ($validated['slug'] ?? '');
        $newSlug = $slug !== '' && $slug !== $course->slug()
            ? $this->uniqueSlug((string) $validated['title'], $slug)
            : null;

        $course->revise(
            title: (string) $validated['title'],
            slug: $newSlug,
            summary: $this->renderBody((string) ($validated['summary'] ?? '')) ?: null,
            description: $this->renderBody((string) ($validated['description'] ?? '')) ?: null,
            categoryId: ($validated['category_id'] ?? null) === null ? null : (int) $validated['category_id'],
            level: CourseLevel::tryFromString((string) ($validated['level'] ?? 'beginner')),
            language: (string) ($validated['language'] ?? 'fa'),
            price: $this->priceFrom($validated, $currency),
            thumbnail: ($validated['thumbnail'] ?? null) === null || $validated['thumbnail'] === '' ? null : (string) $validated['thumbnail'],
            prerequisiteIds: \array_key_exists('prerequisites', $validated)
                ? $this->normalizePrerequisites($validated['prerequisites'], $course->id())
                : null,
        );

        $this->courses->save($course);
        if (\array_key_exists('prerequisites', $validated)) {
            $this->courses->replacePrerequisites($course->id(), $course->prerequisiteIds());
        }

        $this->flushEvents($course);
        $this->invalidateCache();

        return $course;
    }

    /** انتشار دوره — با بررسی ساختار حداقلی. */
    public function publish(Course $course): Course
    {
        $lessons = $this->courses->lessonsOf($course->id());
        if ($lessons === []) {
            throw new ValidationException(
                errors: ['lessons' => ['دوره بدون درس منتشر نمی‌شود.']],
                messageId: 'lms::messages.publish_needs_lesson',
            );
        }

        $course->publish();
        $course->refreshDuration($this->courses->refreshDuration($course->id()));
        $this->courses->save($course);

        $this->flushEvents($course);
        $this->invalidateCache();

        return $course;
    }

    public function archive(Course $course): Course
    {
        $course->archive();
        $this->courses->save($course);
        $this->flushEvents($course);
        $this->invalidateCache();

        return $course;
    }

    public function delete(Course $course): void
    {
        $courseId = $course->id();
        $this->courses->delete($courseId);

        Event::dispatch('lms.course.updated', [
            'course_id' => $courseId,
            'slug' => $course->slug(),
            'title' => $course->title(),
            'status' => 'deleted',
            'change' => 'delete',
        ]);
        $this->invalidateCache();
    }

    // ─── بخش ──────────────────────────────────────────────────────────────

    public function addSection(Course $course, string $title, ?int $position = null): Section
    {
        $title = \trim($title);
        if ($title === '') {
            throw new ValidationException(
                errors: ['title' => ['عنوان بخش لازم است.']],
                messageId: 'lms::messages.section_title_required',
            );
        }

        $existing = $this->courses->structureOf($course->id());
        $section = Section::create($course->id(), $title, $position ?? \count($existing));
        $this->courses->saveSection($section);
        $this->invalidateCache();

        return $section;
    }

    public function updateSection(Section $section, string $title, ?int $position = null): Section
    {
        $section->rename(\trim($title));
        if ($position !== null) {
            $section->move($position);
        }
        $this->courses->saveSection($section);
        $this->invalidateCache();

        return $section;
    }

    public function deleteSection(Section $section): void
    {
        $this->courses->deleteSection($section->id());
        $course = $this->courses->findById($section->courseId());
        if ($course !== null) {
            $course->refreshDuration($this->courses->refreshDuration($course->id()));
            $this->courses->save($course);
        }
        $this->invalidateCache();
    }

    // ─── درس ──────────────────────────────────────────────────────────────

    /**
     * افزودن درس.
     *
     * @param array<string, mixed> $input
     */
    public function addLesson(Course $course, Section $section, array $input): Lesson
    {
        $validated = $this->validateLesson($input);

        $existing = $this->courses->structureOf($course->id());
        $count = 0;
        foreach ($existing as $branch) {
            $count += \count($branch['lessons']);
        }

        $lesson = Lesson::create(
            sectionId: $section->id(),
            courseId: $course->id(),
            title: (string) $validated['title'],
            slug: $this->uniqueLessonSlug($course->id(), (string) $validated['title'], (string) ($validated['slug'] ?? '')),
            type: LessonType::tryFromString((string) ($validated['type'] ?? 'text')),
            content: $this->renderBody((string) ($validated['content'] ?? '')) ?: null,
            videoUrl: $this->safeUrl($validated['video_url'] ?? null),
            attachmentPath: $this->safePath($validated['attachment_path'] ?? null),
            quizId: ($validated['quiz_id'] ?? null) === null || $validated['quiz_id'] === '' ? null : (string) $validated['quiz_id'],
            duration: (int) ($validated['duration'] ?? 0),
            isFreePreview: (bool) ($validated['is_free_preview'] ?? false),
            position: ($validated['position'] ?? null) === null ? $count : (int) $validated['position'],
        );

        $this->courses->saveLesson($lesson);

        $course->refreshDuration($this->courses->refreshDuration($course->id()));
        $this->courses->save($course);
        $this->invalidateCache();

        return $lesson;
    }

    /**
     * ویرایش درس.
     *
     * @param array<string, mixed> $input
     */
    public function updateLesson(Lesson $lesson, array $input): Lesson
    {
        $validated = $this->validateLesson($input);

        $lesson->revise(
            title: (string) $validated['title'],
            slug: ($validated['slug'] ?? '') !== '' && $validated['slug'] !== $lesson->slug()
                ? $this->uniqueLessonSlug($lesson->courseId(), (string) $validated['title'], (string) $validated['slug'])
                : null,
            type: LessonType::tryFromString((string) ($validated['type'] ?? 'text')),
            content: $this->renderBody((string) ($validated['content'] ?? '')) ?: null,
            videoUrl: $this->safeUrl($validated['video_url'] ?? null),
            attachmentPath: $this->safePath($validated['attachment_path'] ?? null),
            quizId: ($validated['quiz_id'] ?? null) === null || $validated['quiz_id'] === '' ? null : (string) $validated['quiz_id'],
            duration: (int) ($validated['duration'] ?? 0),
            isFreePreview: (bool) ($validated['is_free_preview'] ?? false),
            position: ($validated['position'] ?? null) === null ? null : (int) $validated['position'],
        );

        $this->courses->saveLesson($lesson);

        $course = $this->courses->findById($lesson->courseId());
        if ($course !== null) {
            $course->refreshDuration($this->courses->refreshDuration($course->id()));
            $this->courses->save($course);
        }
        $this->invalidateCache();

        return $lesson;
    }

    public function deleteLesson(Lesson $lesson): void
    {
        $this->courses->deleteLesson($lesson->id());

        $course = $this->courses->findById($lesson->courseId());
        if ($course !== null) {
            $course->refreshDuration($this->courses->refreshDuration($course->id()));
            $this->courses->save($course);
        }
        $this->invalidateCache();
    }

    // ─── دسته ─────────────────────────────────────────────────────────────

    public function createCategory(string $title, ?string $description = null): LmsCategory
    {
        $title = \trim($title);
        if ($title === '') {
            throw new ValidationException(
                errors: ['title' => ['عنوان دسته لازم است.']],
                messageId: 'lms::messages.category_title_required',
            );
        }

        $category = LmsCategory::create($title, $this->uniqueCategorySlug($title), $description !== null && \trim($description) !== '' ? \trim($description) : null);
        $id = $this->courses->saveCategory($category);
        $this->invalidateCache();

        return $this->courses->findCategory($id) ?? $category;
    }

    public function deleteCategory(int $id): void
    {
        $this->courses->deleteCategory($id);
        $this->invalidateCache();
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validateCourse(array $input): array
    {
        return Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:200000'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', 'in:beginner,intermediate,advanced'],
            'language' => ['nullable', 'string', 'max:5'],
            'price' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['nullable'],
            'thumbnail' => ['nullable', 'string', 'max:26'],
            'prerequisites' => ['nullable'],
        ], attributes: [
            'title' => 'عنوان دوره',
            'category_id' => 'دسته',
            'price' => 'قیمت',
            'level' => 'سطح',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validateLesson(array $input): array
    {
        return Validator::validate($input, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:video,text,quiz,attachment'],
            'content' => ['nullable', 'string', 'max:200000'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'quiz_id' => ['nullable', 'string', 'max:26'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_free_preview' => ['nullable'],
            'position' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'title' => 'عنوان درس',
            'type' => 'نوع درس',
            'video_url' => 'نشانی ویدیو',
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function priceFrom(array $validated, string $currency): Money
    {
        $isFree = (bool) ($validated['is_free'] ?? false);
        if ($isFree) {
            return Money::zero($currency);
        }

        return Money::of(\max(0, (int) ($validated['price'] ?? 0)), $currency);
    }

    /** Markdown escape-first + sanitize لایهٔ دوم (۰ XSS). */
    private function renderBody(string $raw): string
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return '';
        }

        $html = $this->markdown->toHtml($raw);
        $profile = (string) ($this->config['sanitize']['profile'] ?? 'rich_text');

        // allowed_tags = فهرست مسطح (سانیتایزر attributeهای on* و پروتکل
        // خطرناک را خودش حذف می‌کند — src ویدیو با allowlist پروتکل امن است)
        $allowed = ['p', 'br', 'strong', 'em', 'b', 'i', 'a', 'code', 'pre', 'ul', 'ol', 'li', 'blockquote', 'video'];
        foreach ((array) ($this->config['sanitize']['extra_tags'] ?? []) as $tag) {
            if (\is_string($tag)) {
                $allowed[] = $tag;
            }
        }

        return Security::sanitize($html, $profile, ['allowed_tags' => $allowed]);
    }

    /** 🔒 allowlist نشانی ویدیو — فقط http(s) و مسیر نسبی (ضد javascript:/data:). */
    private function safeUrl(mixed $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = \trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (\preg_match('#^(https?:)?//#i', $url) === 1 || \str_starts_with($url, '/')) {
            return $url;
        }

        throw new ValidationException(
            errors: ['video_url' => ['نشانی ویدیو باید http(s) یا مسیر نسبی باشد.']],
            messageId: 'lms::messages.invalid_video_url',
        );
    }

    /** 🔒 مسیر پیوست — بدون traversal. */
    private function safePath(mixed $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = \trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (\str_contains($path, '..') || \str_starts_with($path, '/')) {
            throw new ValidationException(
                errors: ['attachment_path' => ['مسیر پیوست نامعتبر است.']],
                messageId: 'lms::messages.invalid_attachment_path',
            );
        }

        return $path;
    }

    private function uniqueSlug(string $title, string $explicit): string
    {
        $base = $explicit !== ''
            ? Slug::fromRaw($explicit)->toString()
            : Slug::of($title)->toString();

        if ($base === '') {
            $base = 'course-' . \strtolower(\substr(\bin2hex(\random_bytes(4)), 0, 8));
        }

        $slug = $base;
        $suffix = 2;
        while ($this->courses->slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueLessonSlug(string $courseId, string $title, string $explicit): string
    {
        $base = $explicit !== ''
            ? Slug::fromRaw($explicit)->toString()
            : Slug::of($title)->toString();

        if ($base === '') {
            $base = 'lesson-' . \strtolower(\substr(\bin2hex(\random_bytes(4)), 0, 8));
        }

        // slug درس در دامنهٔ دوره یکتا است (بررسی روی ساختار موجود)
        $taken = [];
        foreach ($this->courses->lessonsOf($courseId) as $lesson) {
            $taken[$lesson->slug()] = true;
        }

        $slug = $base;
        $suffix = 2;
        while (isset($taken[$slug])) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $title): string
    {
        $base = Slug::of($title)->toString() ?: 'category-' . \strtolower(\substr(\bin2hex(\random_bytes(3)), 0, 6));

        $slug = $base;
        $suffix = 2;
        while ($this->courses->findCategoryBySlug($slug) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /** @return list<string> */
    private function normalizePrerequisites(mixed $raw, string $courseId): array
    {
        if ($raw === null) {
            return [];
        }
        if (\is_string($raw)) {
            $raw = \array_filter(\array_map('trim', \explode(',', $raw)));
        }
        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $pid) {
            $pid = \trim((string) $pid);
            if ($pid === '' || $pid === $courseId || isset($ids[$pid])) {
                continue;   // 🔒 پیش‌نیازِ خود = چرخهٔ بدیهی
            }
            if ($this->courses->findById($pid) === null) {
                throw new ValidationException(
                    errors: ['prerequisites' => ['دورهٔ پیش‌نیاز یافت نشد: ' . $pid]],
                    messageId: 'lms::messages.prerequisite_not_found',
                );
            }
            $ids[$pid] = true;
        }

        return \array_keys($ids);
    }

    private function flushEvents(Course $course): void
    {
        foreach ($course->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }

    private function invalidateCache(): void
    {
        try {
            Cache::tags(['lms'])->flush();
        } catch (\Throwable) {
            // درایور بدون tag
        }
    }
}
