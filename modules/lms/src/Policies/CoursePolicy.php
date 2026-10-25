<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CoursePolicy — مجوزهای مدیریت دوره (DOC-095 §4).
 */

namespace Modules\Lms\Policies;

use Modules\Lms\Domain\Entities\Course;
use Tavaan\Core\Contracts\Auth\Authenticatable;
use Tavaan\Core\Facade\Facades\Gate;

/**
 * CoursePolicy — instructor روی دورهٔ **خود** + دارندگان مجوزهای «همه»
 * (lms-admin) روی هر دوره. همان الگوی اعلامی rbac.policies (گزارش M9 گام ۱).
 *
 * @api 1.0
 */
final class CoursePolicy
{
    public function view(Authenticatable $user, Course $course): bool
    {
        return Gate::allows('lms.course.view') || $this->owns($user, $course);
    }

    public function update(Authenticatable $user, Course $course): bool
    {
        return Gate::allows('lms.course.update') || (Gate::allows('lms.course.create') && $this->owns($user, $course));
    }

    public function delete(Authenticatable $user, Course $course): bool
    {
        return Gate::allows('lms.course.delete') || (Gate::allows('lms.course.create') && $this->owns($user, $course));
    }

    public function publish(Authenticatable $user, Course $course): bool
    {
        return Gate::allows('lms.course.publish') || (Gate::allows('lms.course.create') && $this->owns($user, $course));
    }

    /** مدیریت آزمون/سؤال دوره. */
    public function manageQuiz(Authenticatable $user, Course $course): bool
    {
        return Gate::allows('lms.quiz.manage') && ($this->owns($user, $course) || Gate::allows('lms.course.update'));
    }

    /** نمره‌دهی دستی آزمون‌های دوره. */
    public function grade(Authenticatable $user, Course $course): bool
    {
        if (!Gate::allows('lms.attempt.grade')) {
            return false;
        }

        return Gate::allows('lms.attempt.grade-any') || $this->owns($user, $course);
    }

    private function owns(Authenticatable $user, Course $course): bool
    {
        return (int) $user->getAuthIdentifier() === $course->instructorId();
    }
}
