<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * BpmsNotifier — اعلان‌های چندکاناله رویدادهای bpms.* (DOC-098 §10).
 */

namespace Modules\Bpms\Infrastructure\Notifications;

use Modules\Bpms\Domain\Repositories\RoleReaderInterface;
use Modules\CoreUser\Contracts\UserReaderInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Facade\Facades\Lang;
use Tavaan\Core\Facade\Facades\Notification;

/**
 * BpmsNotifier — شنوندهٔ رویدادهای bpms.*:
 *
 * - bpms.process.started    → initiator (database)
 * - bpms.task.created       → assignee کاربر / کاربران نقش (mail+database)
 * - bpms.task.completed     → initiator (پیش‌فرض خاموش)
 * - bpms.process.completed  → initiator (mail+database)
 * - bpms.task.escalated     → نقش‌های escalation (mail+database)
 *
 * ساخت پیام از binding `notification.message` (بدون ارجاع به موتور —
 * الگوی CrmNotifier). همه‌جا best-effort.
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class BpmsNotifier
{
    /** @param array<string, mixed> $config کلید config('bpms') */
    public function __construct(
        private readonly UserReaderInterface $users,
        private readonly RoleReaderInterface $roles,
        private readonly ContainerInterface $container,
        private readonly array $config = [],
    ) {
    }

    /** @param mixed $payload */
    public function onProcessStarted(string $event, $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('process_started')) {
            return;
        }
        $initiator = $payload['initiator_id'] ?? null;
        if ($initiator === null) {
            return;
        }

        $this->notifyUser((int) $initiator, [
            'via' => ['database'],
            'database' => [
                'kind' => $event,
                'instance_id' => (string) ($payload['instance_id'] ?? ''),
                'definition_key' => (string) ($payload['definition_key'] ?? ''),
            ],
        ]);
    }

    /** @param mixed $payload */
    public function onTaskCreated(string $event, $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('task_created')) {
            return;
        }

        $mail = [
            'subject' => Lang::get('bpms::messages.notify_task_subject', ['title' => (string) ($payload['title'] ?? '')]),
            'lines' => [Lang::get('bpms::messages.notify_task_line', ['title' => (string) ($payload['title'] ?? '')])],
            'action' => ['text' => Lang::get('bpms::messages.notify_task_cta'), 'url' => '/bpms/tasks/' . (string) ($payload['task_id'] ?? '')],
        ];
        $database = [
            'kind' => $event,
            'task_id' => (string) ($payload['task_id'] ?? ''),
            'instance_id' => (string) ($payload['instance_id'] ?? ''),
            'title' => (string) ($payload['title'] ?? ''),
        ];

        $type = (string) ($payload['assignee_type'] ?? '');
        $assigneeId = $payload['assignee_id'] ?? null;

        if ($type === 'user' || $type === 'initiator') {
            if ($assigneeId !== null && (string) $assigneeId !== '') {
                $this->notifyUser((int) $assigneeId, ['via' => ['mail', 'database'], 'mail' => $mail, 'database' => $database]);
            }

            return;
        }

        if (($type === 'role' || $type === 'group') && $assigneeId !== null && (string) $assigneeId !== '') {
            foreach ($this->roles->usersOfRoles([(string) $assigneeId]) as $userId) {
                $this->notifyUser((int) $userId, ['via' => ['mail', 'database'], 'mail' => $mail, 'database' => $database]);
            }
        }
    }

    /** @param mixed $payload */
    public function onProcessCompleted(string $event, $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('process_completed')) {
            return;
        }
        $initiator = $payload['initiator_id'] ?? null;
        if ($initiator === null) {
            return;
        }

        $this->notifyUser((int) $initiator, [
            'via' => ['mail', 'database'],
            'mail' => [
                'subject' => Lang::get('bpms::messages.notify_completed_subject', ['key' => (string) ($payload['definition_key'] ?? '')]),
                'lines' => [Lang::get('bpms::messages.notify_completed_line', ['key' => (string) ($payload['definition_key'] ?? '')])],
                'action' => ['text' => Lang::get('bpms::messages.notify_completed_cta'), 'url' => '/bpms/processes/' . (string) ($payload['instance_id'] ?? '')],
            ],
            'database' => [
                'kind' => $event,
                'instance_id' => (string) ($payload['instance_id'] ?? ''),
                'definition_key' => (string) ($payload['definition_key'] ?? ''),
            ],
        ]);
    }

    /** @param mixed $payload */
    public function onTaskEscalated(string $event, $payload): void
    {
        $payload = (array) $payload;
        if (!$this->enabled('task_escalated')) {
            return;
        }

        $mail = [
            'subject' => Lang::get('bpms::messages.notify_escalated_subject', ['title' => (string) ($payload['title'] ?? '')]),
            'lines' => [Lang::get('bpms::messages.notify_escalated_line', ['title' => (string) ($payload['title'] ?? '')])],
            'action' => ['text' => Lang::get('bpms::messages.notify_task_cta'), 'url' => '/bpms/tasks/' . (string) ($payload['task_id'] ?? '')],
        ];
        $database = [
            'kind' => $event,
            'task_id' => (string) ($payload['task_id'] ?? ''),
            'instance_id' => (string) ($payload['instance_id'] ?? ''),
            'escalated_to' => (string) ($payload['escalated_to'] ?? ''),
        ];

        $notifyRoles = (array) ($this->config['notifications']['escalate_notify_roles'] ?? ['process-admin', 'admin']);
        foreach ($this->roles->usersOfRoles($notifyRoles) as $userId) {
            $this->notifyUser((int) $userId, ['via' => ['mail', 'database'], 'mail' => $mail, 'database' => $database]);
        }
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
            // best-effort — اعلان جریان فرایند را نمی‌شکند
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

    private function enabled(string $key): bool
    {
        return (bool) ($this->config['notifications'][$key] ?? true);
    }
}
