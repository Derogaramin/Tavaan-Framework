<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms\Gateways;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\AbstractHttpSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * SmsIrGateway — درگاه SMS.ir (v1 bulk؛ DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class SmsIrGateway extends AbstractHttpSmsGateway
{
    public function name(): string
    {
        return 'smsir';
    }

    protected function endpoint(): string
    {
        return (string) ($this->config('base_url') ?? 'https://api.sms.ir/v1/send/bulk');
    }

    /** @param list<string> $recipients @return array<string, mixed> */
    protected function payload(SmsMessage $message, array $recipients): array
    {
        return [
            'lineNumber' => $this->senderNumber($message),
            'messageText' => $message->content,
            'mobiles' => $recipients,
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
        if ($status === 200 && \is_array($decoded) && (int) ($decoded['status'] ?? 1) === 1) {
            return SmsResult::ok((string) ($decoded['data']['bulkId'] ?? ''));
        }

        return SmsResult::fail(\is_array($decoded) ? (string) ($decoded['message'] ?? $body) : $body);
    }
}
