<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms\Gateways;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\AbstractHttpSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * MelipayamakGateway — درگاه ملی‌پیامک (REST؛ DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class MelipayamakGateway extends AbstractHttpSmsGateway
{
    public function name(): string
    {
        return 'melipayamak';
    }

    protected function endpoint(): string
    {
        return (string) ($this->config('base_url') ?? 'https://sms.melipayamak.com/api/send/simple/v2');
    }

    /** @param list<string> $recipients @return array<string, mixed> */
    protected function payload(SmsMessage $message, array $recipients): array
    {
        return [
            'from' => $this->senderNumber($message),
            'to' => $recipients,
            'text' => $message->content,
        ];
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        $username = (string) ($this->config('username') ?? '');
        $password = (string) ($this->config('password') ?? '');

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . \base64_encode($username . ':' . $password),
        ];
    }

    protected function parseResult(int $status, string $body): SmsResult
    {
        $decoded = \json_decode($body, true);
        if ($status === 200 && \is_array($decoded)) {
            return SmsResult::ok((string) ($decoded['id'] ?? $decoded['Id'] ?? ''), (int) ($decoded['cost'] ?? 0));
        }

        return SmsResult::fail(\is_array($decoded) ? (string) (\json_encode($decoded, \JSON_UNESCAPED_UNICODE) ?: $body) : $body);
    }
}
