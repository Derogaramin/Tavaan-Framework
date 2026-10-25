<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * LmsNotifier — اعلان‌های چندکاناله (DOC-095 §5).
 */

namespace Modules\Lms\Infrastructure\Notifications;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * LmsNotifier — شنوندهٔ رویدادهای lms.*:
 *
 * - lms.student.enrolled   → welcome (mail+database)
 * - lms.course.completed   → تبریک پایان دوره
 * - lms.certificate.issued → لینک گواهی + verify
 * - lms.attempt.graded     → نتیجهٔ آزمون (نمره‌دهی دستی)
 *
 * ساخت پیام از binding `notification.message` (بدون ارجاع به کلاس موتور —
 * الگوی ArrayNotification گزارش M9 گام ۱).
 *
 * @api 1.0
 */
final class LmsNotifier
{
    /** @param array<string, mixed> $config کلید config('lms.notifications') */
    public function __construct(
        private readonly UserReaderInterface $users,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    public function onEnrolled(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('welcome')) {
            return;
        }

        $this->notify((int) ($payload['student_id'] ?? 0), [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('lms::messages.notify_welcome_subject', ['course' => (string) ($payload['course_title'] ?? '')]),
                'lines' => [Lang::get('lms::messages.notify_welcome_line', ['course' => (string) ($payload['course_title'] ?? '')])],
                'action' => [
                    'text' => Lang::get('lms::messages.notify_start_learning'),
                    'url' => '/lms/learn',
                ],
            ],
            'database' => [
                'kind' => 'lms.student.enrolled',
                'course_id' => (string) ($payload['course_id'] ?? ''),
                'enrollment_id' => (string) ($payload['enrollment_id'] ?? ''),
            ],
        ]);
    }

    public function onCourseCompleted(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('course_completed')) {
            return;
        }

        $this->notify((int) ($payload['student_id'] ?? 0), [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('lms::messages.notify_completed_subject', ['course' => (string) ($payload['course_title'] ?? '')]),
                'lines' => [Lang::get('lms::messages.notify_completed_line', ['course' => (string) ($payload['course_title'] ?? '')])],
                'action' => [
                    'text' => Lang::get('lms::messages.notify_cta_learn'),
                    'url' => '/lms/learn',
                ],
            ],
            'database' => [
                'kind' => 'lms.course.completed',
                'course_id' => (string) ($payload['course_id'] ?? ''),
            ],
        ]);
    }

    public function onCertificateIssued(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('certificate_issued')) {
            return;
        }

        $this->notify((int) ($payload['student_id'] ?? 0), [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('lms::messages.notify_certificate_subject', ['course' => (string) ($payload['course_title'] ?? '')]),
                'lines' => [Lang::get('lms::messages.notify_certificate_line', [
                    'course' => (string) ($payload['course_title'] ?? ''),
                    'serial' => (string) ($payload['serial'] ?? ''),
                ])],
                'action' => [
                    'text' => Lang::get('lms::messages.notify_certificate_cta'),
                    'url' => (string) ($payload['verify_url'] ?? '/lms/learn'),
                ],
            ],
            'database' => [
                'kind' => 'lms.certificate.issued',
                'serial' => (string) ($payload['serial'] ?? ''),
                'course_id' => (string) ($payload['course_id'] ?? ''),
            ],
        ]);
    }

    public function onAttemptGraded(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('attempt_graded')) {
            return;
        }

        $this->notify((int) ($payload['student_id'] ?? 0), [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('lms::messages.notify_graded_subject'),
                'lines' => [Lang::get('lms::messages.notify_graded_line', [
                    'score' => (string) ($payload['score'] ?? '0'),
                    'max' => (string) ($payload['max_score'] ?? '0'),
                    'result' => ((bool) ($payload['passed'] ?? false))
                        ? Lang::get('lms::messages.quiz_passed')
                        : Lang::get('lms::messages.quiz_failed'),
                ])],
                'action' => [
                    'text' => Lang::get('lms::messages.notify_cta_learn'),
                    'url' => '/lms/learn',
                ],
            ],
            'database' => [
                'kind' => 'lms.attempt.graded',
                'attempt_id' => (string) ($payload['attempt_id'] ?? ''),
                'passed' => (bool) ($payload['passed'] ?? false),
            ],
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $payload */
    private function notify(int $userId, array $payload): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $student = $this->users->findNotifiable($userId);
            if ($student === null) {
                return;
            }

            $notification = $this->makeNotification($payload);
            if ($notification !== null) {
                Notification::send($student, $notification);
            }
        } catch (\Throwable) {
            // best-effort — اعلان جریان دامنه را نمی‌شکند
        }
    }

    /** @param array<string, mixed> $payload */
    private function makeNotification(array $payload): ?object
    {
        if (!$this->container->bound('notification.message')) {
            return null;
        }

        $notification = $this->container->make('notification.message', ['payload' => $payload]);

        return \is_object($notification) ? $notification : null;
    }

    private function enabled(string $key): bool
    {
        return (bool) ($this->config[$key] ?? true);
    }
}
