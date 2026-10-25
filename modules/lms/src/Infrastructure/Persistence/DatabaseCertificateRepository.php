<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * DatabaseCertificateRepository — آداپتر گواهی (verify عمومی با serial).
 */

namespace Modules\Lms\Infrastructure\Persistence;

use Modules\Lms\Domain\Entities\Certificate;
use Modules\Lms\Domain\Repositories\CertificateRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/lms/README.md
 */
final class DatabaseCertificateRepository implements CertificateRepositoryInterface
{
    public function save(Certificate $certificate): void
    {
        $row = $certificate->toRow();
        $exists = DB::table('lms_certificates')->where('id', $certificate->id())->exists();

        if ($exists) {
            unset($row['id'], $row['issued_at']);
            DB::table('lms_certificates')->where('id', $certificate->id())->update($row);
        } else {
            DB::table('lms_certificates')->insert($row);
        }
    }

    public function findById(string $id): ?Certificate
    {
        $row = DB::table('lms_certificates')->where('id', $id)->first();

        return $row === null ? null : Certificate::fromRow($row);
    }

    public function findBySerial(string $serial): ?Certificate
    {
        $row = DB::table('lms_certificates')->where('serial', $serial)->first();

        return $row === null ? null : Certificate::fromRow($row);
    }

    public function findForEnrollment(string $enrollmentId): ?Certificate
    {
        $row = DB::table('lms_certificates')
            ->where('enrollment_id', $enrollmentId)
            ->orderByDesc('issued_at')
            ->first();

        return $row === null ? null : Certificate::fromRow($row);
    }

    public function serialExists(string $serial): bool
    {
        return DB::table('lms_certificates')->where('serial', $serial)->exists();
    }

    public function forStudent(int $studentId): array
    {
        $rows = DB::table('lms_certificates')->where('student_id', $studentId)->orderByDesc('issued_at')->get();

        return \array_map(static fn (array $row): Certificate => Certificate::fromRow($row), $rows);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('lms_certificates');
        $rows = DB::table('lms_certificates');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['course_id']) && (string) $filters['course_id'] !== '') {
                $q->where('course_id', (string) $filters['course_id']);
            }
            if (isset($filters['q']) && (string) $filters['q'] !== '') {
                $q->where('serial', 'LIKE', '%' . (string) $filters['q'] . '%');
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('issued_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Certificate => Certificate::fromRow($row), $data),
            'total' => $total,
        ];
    }
}
