<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * CertificateRepositoryInterface — پورت مخزن گواهی.
 */

namespace Modules\Lms\Domain\Repositories;

use Modules\Lms\Domain\Entities\Certificate;

/**
 * @api 1.0
 */
interface CertificateRepositoryInterface
{
    public function save(Certificate $certificate): void;

    public function findById(string $id): ?Certificate;

    /** راستی‌آزمایی عمومی با serial (DOC-095 §3.4: verify_url). */
    public function findBySerial(string $serial): ?Certificate;

    /** گواهی صادرشدهٔ یک ثبت‌نام (idempotency صدور). */
    public function findForEnrollment(string $enrollmentId): ?Certificate;

    /** آیا serial گرفته شده؟ (تولید serial یکتا) */
    public function serialExists(string $serial): bool;

    /** گواهی‌های دانشجو. @return list<Certificate> */
    public function forStudent(int $studentId): array;

    /**
     * فهرست مدیریتی.
     *
     * @param array{status?: string|null, course_id?: string|null, q?: string} $filters
     *
     * @return array{rows: list<Certificate>, total: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array;
}
