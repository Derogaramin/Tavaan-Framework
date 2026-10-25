<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * ErpAuditRecorder — پل رویدادهای مالی ERP به موتور audit (DOC-097 §7).
 */

namespace Modules\Erp\Application\UseCases;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Audit;
use Tavaan\Core\Facade\Facades\Config;
use Tavaan\Engine\Audit\AuditEvent;   // allow صریح ARCH (VO readonly — الگوی SDK مانند Console\Command)

/**
 * ErpAuditRecorder — هر رویداد مالی (سند/تأیید/ثبت/ابطال/رسید/حقوق/بستن دوره)
 * با hash chain موتور audit ثبت می‌شود:
 *
 * - تغییرات با رد کاربر (userId) و مقادیر جدید
 * - غیرقابل حذف — `tvn audit:verify` زنجیره را راستی‌آزمایی می‌کند (AC-ERP-10)
 *
 * اگر موتور audit نصب/فعال نباشد → no-op (fail-open، بدون شکست جریان مالی).
 *
 * @api 1.0
 */
final class ErpAuditRecorder
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param array<string, mixed> $values */
    public function record(string $action, string $entityType, string $entityId, array $values = [], ?int $userId = null): void
    {
        if (!Config::get('erp.audit.enabled', true) || !$this->available()) {
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
            // fail-open — جریان مالی نباید با خطای audit متوقف شود
        }
    }

    /** زنجیرهٔ audit سالم است؟ (داشبورد/صفحهٔ audit). */
    public function chainHealthy(): bool
    {
        if (!$this->available()) {
            return true;
        }

        try {
            return (bool) Audit::verify()->intact;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> آخرین رویدادهای زنجیره */
    public function trail(int $limit = 100): array
    {
        if (!$this->available()) {
            return [];
        }

        try {
            // export با limit بزرگ‌تر — سپس N ردیف آخر (جدیدترین)
            $rows = Audit::export(limit: 5000);

            return \array_slice(\array_reverse($rows), 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    private function available(): bool
    {
        return $this->container->bound('audit');
    }
}
