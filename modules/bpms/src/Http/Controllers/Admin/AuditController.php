<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * AuditController — ردپای audit عملیات مدیریتی (E2E-09).
 */

namespace Modules\Bpms\Http\Controllers\Admin;

use Modules\Bpms\Application\UseCases\BpmsAuditRecorder;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class AuditController
{
    public function __construct(private readonly BpmsAuditRecorder $audit)
    {
    }

    public function index(): mixed
    {
        if (!Gate::allows('bpms.audit.view')) {
            throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => 'bpms.audit.view']);
        }

        $rows = [];
        foreach ($this->audit->trail(200) as $row) {
            // فقط رویدادهای BPMS (حذف نویز سایر ماژول‌ها)
            if (\str_starts_with((string) ($row['auditable_type'] ?? ''), 'bpms_')) {
                $rows[] = $row;
            }
        }

        return View::render('bpms::admin.audit', [
            'rows' => $rows,
            'chainHealthy' => $this->audit->chainHealthy(),
        ]);
    }
}
