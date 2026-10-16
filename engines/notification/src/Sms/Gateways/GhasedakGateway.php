<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms\Gateways;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\AbstractHttpSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * GhasedakGateway — درگاه قاصدک (DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class GhasedakGateway extends AbstractHttpSmsGateway
{
    public function name(): string
    {
        return 'ghasedak';
    }

    protected function endpoint(): string
    {
        return (string) ($this->config('base_url') ?? 'https://api.ghasedak.me/v2/sms/send/simple');
    }

    /** @param list<string> $recipients @return array<string, mixed> */
    protected function payload(SmsMessage $message, array $recipients): array
    {
        return [
            'linenumber' => $this->senderNumber($message),
            'message' => $message->content,
            'receptor' => $recipients,
        ];
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        return ['x-api-key' => (string) ($this->config('api_key') ?? '')];
    }

    protected function parseResult(int $status, string $body): SmsResult
    {
        $decoded = \json_decode($body, true);
        if ($status === 200 && \is_array($decoded) && (bool) ($decoded['success'] ?? false)) {
            $items = $decoded['result']['items'] ?? [];
            $id = \is_array($items) ? (string) ($items[0] ?? '') : '';

            return SmsResult::ok($id);
        }

        return SmsResult::fail(\is_array($decoded) ? (string) ($decoded['message'] ?? $body) : $body);
    }
}
