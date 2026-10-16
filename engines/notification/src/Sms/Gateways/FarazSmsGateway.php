<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms\Gateways;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\AbstractHttpSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * FarazSmsGateway — درگاه فراز اس‌ام‌اس (ip.panel.com؛ DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class FarazSmsGateway extends AbstractHttpSmsGateway
{
    public function name(): string
    {
        return 'farazsms';
    }

    protected function endpoint(): string
    {
        return (string) ($this->config('base_url') ?? 'https://ip.panel.com/api/select');
    }

    /** @param list<string> $recipients @return array<string, mixed> */
    protected function payload(SmsMessage $message, array $recipients): array
    {
        return [
            'op' => 'send',
            'user' => (string) ($this->config('username') ?? ''),
            'pass' => (string) ($this->config('password') ?? ''),
            'fromNum' => $this->senderNumber($message),
            'toNum' => $recipients,
            'messageContent' => $message->content,
        ];
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    protected function parseResult(int $status, string $body): SmsResult
    {
        $decoded = \json_decode($body, true);
        if ($status === 200 && \is_array($decoded) && (string) ($decoded['status'] ?? '') === 'ok') {
            return SmsResult::ok((string) ($decoded['data']['batchId'] ?? ''));
        }

        return SmsResult::fail(\is_array($decoded) ? (string) ($decoded['message'] ?? $body) : $body);
    }
}
