<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * RecomputeProgressCommand — `lms:recompute-progress` (بازشماری پیشرفت).
 */

namespace Modules\Lms\Console\Commands;

use Modules\Lms\Application\UseCases\ProgressService;
use Modules\Lms\Domain\Repositories\EnrollmentRepositoryInterface;
use Tavaan\Engine\Console\Command;

/**
 * بازشماری درصد پیشرفت و وضعیت تکمیل ثبت‌نام‌ها (نگهداری):
 *
 * - پس از import/مهاجرت داده یا حذف درس‌ها
 * - ناهمگامی احتمالی progress_percent با ردیف‌های lms_lesson_progress
 *
 * `tvn lms:recompute-progress [--course=<ulid>] [--dry-run]`
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final class RecomputeProgressCommand extends Command
{
    protected string $signature = 'lms:recompute-progress {--course= : فقط یک دوره (ULID)} {--dry-run : فقط گزارش، بدون نوشتن}';

    protected string $description = 'بازشماری پیشرفت ثبت‌نام‌های LMS';

    public function handle(): int
    {
        /** @var EnrollmentRepositoryInterface $enrollments */
        $enrollments = $this->app->make(EnrollmentRepositoryInterface::class);
        /** @var ProgressService $progress */
        $progress = $this->app->make(ProgressService::class);

        $dryRun = (bool) $this->option('dry-run');
        $courseFilter = (string) ($this->option('course') ?? '');

        $filters = $courseFilter !== '' ? ['course_id' => $courseFilter] : [];

        $page = 1;
        $scanned = 0;
        $changed = 0;

        do {
            $result = $enrollments->paginate($filters, $page, 100);
            foreach ($result['rows'] as $enrollment) {
                $scanned++;

                $before = $enrollment->progressPercent();
                $statusBefore = $enrollment->status();

                if ($dryRun) {
                    $fresh = $enrollments->findById($enrollment->id());
                    if ($fresh !== null) {
                        $this->line(\sprintf('  %s — %.2f%% (%s)', $fresh->id(), $fresh->progressPercent(), $fresh->status()->value));
                    }

                    continue;
                }

                $fresh = $progress->recompute($enrollment);
                if ($fresh->progressPercent() !== $before || $fresh->status() !== $statusBefore) {
                    $changed++;
                    $this->line(\sprintf(
                        '  %s — %.2f%% → %.2f%% (%s → %s)',
                        $fresh->id(),
                        $before,
                        $fresh->progressPercent(),
                        $statusBefore->value,
                        $fresh->status()->value,
                    ));
                }
            }
            $page++;
        } while (!$dryRun && ($page - 1) * 100 < ($result['total'] ?? 0));

        $this->info(\sprintf('بررسی %d ثبت‌نام — %s', $scanned, $dryRun ? 'حالت dry-run' : ($changed . ' مورد به‌روزرسانی شد')));

        return self::SUCCESS;
    }
}
