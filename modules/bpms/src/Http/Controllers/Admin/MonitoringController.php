<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * MonitoringController — داشبورد پایش + CSV (DOC-098 §8).
 */

namespace Modules\Bpms\Http\Controllers\Admin;

use Modules\Bpms\Application\UseCases\MonitoringService;
use Modules\Bpms\Domain\Repositories\DefinitionRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/bpms/README.md
 */
final class MonitoringController
{
    public function __construct(
        private readonly MonitoringService $monitoring,
        private readonly DefinitionRepositoryInterface $definitions,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('bpms.monitor.view');

        $definitionKey = (string) $request->input('key', '');
        $data = $this->monitoring->dashboard($definitionKey === '' ? null : $definitionKey);

        // فهرست کلیدهای فعال برای فیلتر
        $keys = [];
        foreach ($this->definitions->paginate(['active' => true], 1, 100)['rows'] as $definition) {
            $keys[$definition->key()] = $definition->name();
        }

        return View::render('bpms::admin.monitoring', [
            'data' => $data,
            'keys' => $keys,
            'activeKey' => $definitionKey,
        ]);
    }

    public function csv(RequestInterface $request): mixed
    {
        $this->authorize('bpms.monitor.view');

        $definitionKey = (string) $request->input('key', '');
        $csv = $this->monitoring->toCsv($this->monitoring->csvRows($definitionKey === '' ? null : $definitionKey));

        /** @var object $responses */
        $responses = \app()->make('response.factory');

        return $responses->make(
            $csv,
            200,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="bpms-kpis.csv"'],
        );
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'bpms::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
