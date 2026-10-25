<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CampaignAdminController — مدیریت کمپین‌ها (/admin/crm/campaigns).
 */

namespace Modules\Crm\Http\Controllers\Admin;

use Modules\Crm\Application\UseCases\CampaignService;
use Modules\Crm\Domain\Entities\Campaign;
use Modules\Crm\Domain\Repositories\CampaignRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Gate;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class CampaignAdminController
{
    public function __construct(
        private readonly CampaignService $service,
        private readonly CampaignRepositoryInterface $campaigns,
    ) {
    }

    public function index(RequestInterface $request): mixed
    {
        $this->authorize('crm.campaign.view');

        $page = \max(1, (int) $request->input('page', 1));
        $filters = [];
        if (($status = (string) $request->input('status', '')) !== '') {
            $filters['status'] = $status;
        }

        $result = $this->campaigns->paginate($filters, $page, 20);

        return View::render('crm::admin.campaigns-index', [
            'campaigns' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'statusFilter' => (string) $request->input('status', ''),
        ]);
    }

    public function create(): mixed
    {
        $this->authorize('crm.campaign.manage');

        return View::render('crm::admin.campaign-form', ['campaign' => null]);
    }

    public function store(RequestInterface $request): mixed
    {
        $this->authorize('crm.campaign.manage');

        try {
            $campaign = $this->service->create($this->campaignInput($request));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'campaign_created');

        return redirect('/admin/crm/campaigns/' . $campaign->id() . '/edit');
    }

    public function edit(string $id): mixed
    {
        $campaign = $this->findOr404($id);
        $this->authorize('crm.campaign.view');

        return View::render('crm::admin.campaign-form', ['campaign' => $campaign]);
    }

    public function update(RequestInterface $request, string $id): mixed
    {
        $campaign = $this->findOr404($id);
        $this->authorize('crm.campaign.manage');

        try {
            $this->service->update($campaign, $this->campaignInput($request));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        Session::flash('status', 'campaign_updated');

        return back();
    }

    /** ارسال فوری کمپین (E2E-04). */
    public function send(string $id): mixed
    {
        $campaign = $this->findOr404($id);
        $this->authorize('crm.campaign.send');

        $result = $this->service->send($campaign);

        Session::flash('status', 'campaign_sent');
        Session::flash('campaign_result', $result);

        return redirect('/admin/crm/campaigns/' . $campaign->id() . '/edit');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function campaignInput(RequestInterface $request): array
    {
        return [
            'name' => (string) $request->input('name', ''),
            'type' => (string) $request->input('type', 'email'),
            'template' => (string) $request->input('template', ''),
            'audience' => (string) $request->input('audience', ''),
            'scheduled_at' => (string) $request->input('scheduled_at', ''),
        ];
    }

    private function findOr404(string $id): Campaign
    {
        $campaign = $this->campaigns->findById($id);
        if ($campaign === null) {
            throw new RouteNotFoundException(messageId: 'crm::messages.campaign_not_found', context: ['id' => $id]);
        }

        return $campaign;
    }

    private function authorize(string $permission): void
    {
        if (!Gate::allows($permission)) {
            throw new ForbiddenException(messageId: 'crm::messages.forbidden', context: ['permission' => $permission]);
        }
    }
}
