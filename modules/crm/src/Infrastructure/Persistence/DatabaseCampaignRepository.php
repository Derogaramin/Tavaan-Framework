<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * DatabaseCampaignRepository — آداپتر کمپین + گیرندگان.
 */

namespace Modules\Crm\Infrastructure\Persistence;

use Modules\Crm\Domain\Entities\Campaign;
use Modules\Crm\Domain\Repositories\CampaignRepositoryInterface;
use Tavaan\Core\Facade\Facades\DB;

/**
 * @api 1.0
 * @see modules/crm/README.md
 */
final class DatabaseCampaignRepository implements CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void
    {
        $row = $campaign->toRow();
        $exists = DB::table('crm_campaigns')->where('id', $campaign->id())->exists();

        if ($exists) {
            unset($row['id'], $row['created_at']);
            DB::table('crm_campaigns')->where('id', $campaign->id())->update($row);
        } else {
            DB::table('crm_campaigns')->insert($row);
        }
    }

    public function findById(string $id): ?Campaign
    {
        $row = DB::table('crm_campaigns')->where('id', $id)->first();

        return $row === null ? null : Campaign::fromRow($row);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = \max(1, $page);
        $perPage = \max(1, \min(100, $perPage));

        $count = DB::table('crm_campaigns');
        $rows = DB::table('crm_campaigns');

        $apply = static function (mixed $q) use ($filters): void {
            if (isset($filters['status']) && (string) $filters['status'] !== '') {
                $q->where('status', (string) $filters['status']);
            }
            if (isset($filters['type']) && (string) $filters['type'] !== '') {
                $q->where('type', (string) $filters['type']);
            }
        };
        $apply($count);
        $apply($rows);

        $total = $count->count();
        $data = $rows->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        return [
            'rows' => \array_map(static fn (array $row): Campaign => Campaign::fromRow($row), $data),
            'total' => $total,
        ];
    }

    public function dueScheduled(\DateTimeImmutable $now, int $limit = 20): array
    {
        $rows = DB::table('crm_campaigns')
            ->where('status', 'scheduled')
            ->where(static function (mixed $q) use ($now): void {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $now->format('Y-m-d H:i:s.v'));
            })
            ->orderBy('created_at')
            ->limit(\max(1, $limit))
            ->get();

        return \array_map(static fn (array $row): Campaign => Campaign::fromRow($row), $rows);
    }

    public function recipientExists(string $campaignId, string $contactId): bool
    {
        return DB::table('crm_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('contact_id', $contactId)
            ->exists();
    }

    public function recordRecipient(string $campaignId, string $contactId, string $address, bool $success): void
    {
        DB::table('crm_campaign_recipients')->insert([
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'address' => $address,
            'status' => $success ? 'sent' : 'failed',
            'sent_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
        ]);
    }
}
