<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * ReportAdminController — گزارش‌های CRM (DOC-096 §6).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Application\UseCases\CrmReportService;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class ReportAdminController
{
    public function __construct(
        private readonly CrmReportService $reports,
        private readonly UserReaderInterface $users,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('crm.report.view');

        $overview = $this->reports->overview();

        // نام کارشناسان برای جدول عملکرد
        $agentNames = [];
        foreach ($overview['agents'] as $agent) {
            $agentNames[(int) $agent['owner_id']] = $this->users->displayNameOf((int) $agent['owner_id'], '—');
        }

        return View::render('crm::admin.report', [
            'overview' => $overview,
            'agentNames' => $agentNames,
        ]);
    }

    public function csv(RequestInterface $request): mixed
    {
        $this->authorize('crm.report.view');

        $csv = $this->reports->toCsv($this->reports->overview());

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            $csv,
            200,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="crm-report.csv"'],
        );
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
