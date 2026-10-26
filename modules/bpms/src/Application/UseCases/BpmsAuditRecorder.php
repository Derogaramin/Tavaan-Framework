<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * BpmsAuditRecorder — پل عملیات مدیریتی BPMS به موتور audit (hash chain).
 */

namespace Modules\Bpms\Application\UseCases;

use Tavaan\Core\Facade\Facades\Audit;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Engine\Audit\AuditEvent;   // allow صریح ARCH (VO readonly — الگوی SDK مانند Console\Command)

/**
 * BpmsAuditRecorder — عملیات حساس مدیریتی (publish/activate/retire/
 * terminate/suspend/force-complete) با hash chain موتور audit ثبت می‌شود
 * تا «history + audit کامل» باشد (E2E-09). تاریخچهٔ خودِ فرایند در
 * `bpms_history` است؛ این لایه ردپای غیرقابل‌حذف مدیریتی adds.
 *
 * اگر موتور audit فعال نباشد → no-op (fail-open — الگوی ERP).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class BpmsAuditRecorder
{
    /** @param array<string, mixed> $values */
    public function record(string $action, string $entityType, string $entityId, array $values = [], ?int $userId = null): void
    {
        if (!Config::get('bpms.audit.enabled', true)) {
            return;
        }

        try {
            Audit::record(new AuditEvent(
                name: $action,
                auditableType: $entityType,
                auditableId: $entityId,
                userId: $userId,
                newValues: $values,
            ));
        } catch (\Throwable) {
            // fail-open — جریان فرایند نباید با خطای audit متوقف شود
        }
    }

    /** زنجیرهٔ audit سالم است؟ */
    public function chainHealthy(): bool
    {
        try {
            return (bool) Audit::verify()->intact;
        } catch (\Throwable) {
            return true;   // موتور در دسترس نیست — بی‌اثر
        }
    }

    /** @return list<array<string, mixed>> آخرین رویدادهای زنجیره */
    public function trail(int $limit = 100): array
    {
        try {
            $rows = Audit::export(limit: 5000);

            return \array_slice(\array_reverse($rows), 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
