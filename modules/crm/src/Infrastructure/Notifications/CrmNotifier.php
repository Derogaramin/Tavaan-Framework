<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * CrmNotifier — اعلان‌های چندکاناله (DOC-096 §5).
 */

namespace Modules\Crm\Infrastructure\Notifications;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Crm\Domain\Repositories\ContactRepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Log;
use Tavaan\Core\Facade\Facades\Mail;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * CrmNotifier — شنوندهٔ رویدادهای crm.*:
 *
 * - crm.ticket.created    → کارشناس assigned (mail+database)
 * - crm.ticket.sla_breached → نقش‌های escalation (config crm.sla.escalate_roles)
 * - crm.ticket.resolved   → مشتری (email — رضایت‌سنجی §5)
 * - crm.opportunity.won   → مالک + مدیر (جشن/فاکتور §5)
 * - crm.lead.created      → مالک (assignment)
 *
 * ساخت پیام از binding `notification.message` (بدون ارجاع به موتور —
 * الگوی ArrayNotification). مشتریِ بدون حساب کاربری → Mail مستقیم.
 *
 * @api 1.0
 */
final class CrmNotifier
{
    /** @param array<string, mixed> $config کلید config('crm') */
    public function __construct(
        private readonly UserReaderInterface $users,
        private readonly ContactRepositoryInterface $contacts,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    public function onTicketCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('ticket_assigned')) {
            return;
        }

        $assignedTo = $payload['assigned_to'] ?? null;
        if ($assignedTo === null) {
            return;
        }

        $this->notifyUser((int) $assignedTo, [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('crm::messages.notify_ticket_assigned_subject', ['number' => (string) ($payload['ticket_number'] ?? '')]),
                'lines' => [Lang::get('crm::messages.notify_ticket_assigned_line', [
                    'number' => (string) ($payload['ticket_number'] ?? ''),
                    'subject' => (string) ($payload['subject'] ?? ''),
                ])],
                'action' => ['text' => Lang::get('crm::messages.notify_ticket_cta'), 'url' => '/admin/crm/tickets/' . (string) ($payload['ticket_id'] ?? '')],
            ],
            'database' => [
                'kind' => $event,
                'ticket_id' => (string) ($payload['ticket_id'] ?? ''),
                'ticket_number' => (string) ($payload['ticket_number'] ?? ''),
            ],
        ]);
    }

    public function onSlaBreached(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('sla_breached')) {
            return;
        }

        // escalation به نقش‌های config — پیدا کردن کاربران نقش از جداول RBAC (best-effort)
        foreach ($this->usersOfRoles((array) ($this->config['sla']['escalate_roles'] ?? [])) as $userId) {
            $this->notifyUser((int) $userId, [
                'via' => ['mail', 'database'],
                'mail' => [
                    'subject' => Lang::get('crm::messages.notify_sla_subject', ['number' => (string) ($payload['ticket_number'] ?? '')]),
                    'lines' => [Lang::get('crm::messages.notify_sla_line', [
                        'number' => (string) ($payload['ticket_number'] ?? ''),
                        'minutes' => (string) ($payload['overdue_minutes'] ?? '0'),
                    ])],
                    'action' => ['text' => Lang::get('crm::messages.notify_ticket_cta'), 'url' => '/admin/crm/tickets/' . (string) ($payload['ticket_id'] ?? '')],
                ],
                'database' => [
                    'kind' => 'crm.ticket.sla_breached',
                    'ticket_id' => (string) ($payload['ticket_id'] ?? ''),
                    'overdue_minutes' => (int) ($payload['overdue_minutes'] ?? 0),
                ],
            ]);
        }
    }

    public function onTicketResolved(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('ticket_resolved')) {
            return;
        }

        $contact = isset($payload['contact_id']) ? $this->contacts->findById((string) $payload['contact_id']) : null;
        if ($contact === null || $contact->email() === null) {
            return;
        }

        // مشتری (ممکن است حساب کاربری نداشته باشد) → Mail مستقیم + پایگاه در صورت وجود کاربر
        try {
            Mail::raw(
                Lang::get('crm::messages.notify_resolved_body', [
                    'name' => $contact->fullName(),
                    'number' => (string) ($payload['ticket_number'] ?? ''),
                ]),
                static function (mixed $message) use ($contact, $payload): void {
                    if (\is_object($message) && \method_exists($message, 'to')) {
                        $message->to((string) $contact->email());
                    }
                    if (\is_object($message) && \method_exists($message, 'subject')) {
                        $message->subject(Lang::get('crm::messages.notify_resolved_subject', ['number' => (string) ($payload['ticket_number'] ?? '')]));
                    }
                },
            );
        } catch (\Throwable $e) {
            Log::warning('crm.notify.resolved_mail_failed', ['error' => $e->getMessage()]);
        }

        if ($contact->userId() !== null) {
            $this->notifyUser($contact->userId(), [
                'via' => ['database'],
                'database' => [
                    'kind' => 'crm.ticket.resolved',
                    'ticket_id' => (string) ($payload['ticket_id'] ?? ''),
                ],
            ]);
        }
    }

    public function onOpportunityWon(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('opportunity_won')) {
            return;
        }

        $ownerId = $payload['owner_id'] ?? null;
        if ($ownerId === null) {
            return;
        }

        $this->notifyUser((int) $ownerId, [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('crm::messages.notify_won_subject', ['title' => (string) ($payload['title'] ?? '')]),
                'lines' => [Lang::get('crm::messages.notify_won_line', ['title' => (string) ($payload['title'] ?? '')])],
                'action' => ['text' => Lang::get('crm::messages.notify_won_cta'), 'url' => '/admin/crm/opportunities/' . (string) ($payload['opportunity_id'] ?? '')],
            ],
            'database' => [
                'kind' => 'crm.opportunity.won',
                'opportunity_id' => (string) ($payload['opportunity_id'] ?? ''),
                'value_minor' => (int) ($payload['value_minor'] ?? 0),
            ],
        ]);
    }

    public function onLeadCreated(string $event, mixed $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('lead_assigned')) {
            return;
        }

        $ownerId = $payload['owner_id'] ?? null;
        if ($ownerId === null) {
            return;
        }

        $this->notifyUser((int) $ownerId, [
            'via' => ['database'],
            'database' => [
                'kind' => 'crm.lead.created',
                'lead_id' => (string) ($payload['lead_id'] ?? ''),
                'title' => (string) ($payload['title'] ?? ''),
            ],
        ]);
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $payload */
    private function notifyUser(int $userId, array $payload): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $user = $this->users->findNotifiable($userId);
            if ($user === null) {
                return;
            }

            $notification = $this->makeNotification($payload);
            if ($notification !== null) {
                Notification::send($user, $notification);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** @param array<string, mixed> $payload */
    private function makeNotification(array $payload): ?object
    {
        if (!$this->container->bound('notification.message')) {
            return null;
        }

        $notification = $this->container->make('notification.message', ['payload' => $payload]);

        return \is_object($notification) ? $notification : null;
    }

    /**
     * کاربران نقش‌ها از جداول RBAC (بدون ارجاع به موتور — best-effort).
     *
     * @param list<string> $roles
     *
     * @return list<int>
     */
    private function usersOfRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        try {
            $roleRows = DB::table('tvn_roles')->whereIn('key', $roles)->get();
            $roleIds = [];
            foreach ($roleRows as $row) {
                $roleIds[] = (int) $row['id'];
            }
            if ($roleIds === []) {
                return [];
            }

            $links = DB::table('tvn_role_user')->whereIn('role_id', $roleIds)->get();
            $userIds = [];
            foreach ($links as $link) {
                $userIds[(int) $link['user_id']] = true;
            }

            return \array_keys($userIds);
        } catch (\Throwable) {
            return [];   // جداول RBAC نبود — best-effort (الگوی moderator-finder انجمن)
        }
    }

    private function enabled(string $key): bool
    {
        return (bool) ($this->config['notifications'][$key] ?? true);
    }
}
