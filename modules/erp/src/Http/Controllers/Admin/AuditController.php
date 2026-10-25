<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول ERP
 * AuditController — ردپای audit مالی (DOC-097 §7).
 */

namespace Modules\Erp\Http\Controllers\Admin;

use Modules\Erp\Application\UseCases\ErpAuditRecorder;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/erp/README.md
 */
final class AuditController
{
    public function __construct(private readonly ErpAuditRecorder $audit)
    {
    }

    public function index(): mixed
    {
        if (!Gate::allows('erp.audit.view')) {
            throw new ForbiddenException(messageId: 'erp::messages.forbidden', context: ['permission' => 'erp.audit.view']);
        }

        return View::render('erp::admin.audit-index', [
            'entries' => $this->audit->trail(100),
            'healthy' => $this->audit->chainHealthy(),
        ]);
    }
}
