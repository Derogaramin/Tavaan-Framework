<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Sms\Gateways;

use Tavaan\Engine\Notification\Messages\SmsMessage;
use Tavaan\Engine\Notification\Sms\AbstractHttpSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsResult;

/**
 * تَوان | Tavaan — Notification
 * KavenegarGateway — درگاه کاوه‌نگار (API v1؛ DOC-066 §5).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class KavenegarGateway extends AbstractHttpSmsGateway
{
    public function name(): string
    {
        return 'kavenegar';
    }

    protected function endpoint(): string
    {
        $base = (string) ($this->config('base_url') ?? 'https://api.kavenegar.com/v1');
        $apiKey = (string) ($this->config('api_key') ?? '');

        return $base . '/' . $apiKey . '/sms/send.json';
    }

    /** @param list<string> $recipients @return array<string, mixed> */
    protected function payload(SmsMessage $message, array $recipients): array
    {
        // کاوه‌نگار GET-like params می‌گیرد — به‌شکل JSON body ارسال و با query ترکیب می‌شود
        return [
            'receptor' => \implode(',', $recipients),
            'message' => $message->content,
            'sender' => $this->senderNumber($message),
        ];
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    protected function parseResult(int $status, string $body): SmsResult
    {
        $decoded = \json_decode($body, true);
        if ($status === 200 && \is_array($decoded) && (int) ($decoded['return']['status'] ?? 0) === 200) {
            $entries = $decoded['entries'] ?? [];
            $id = \is_array($entries) ? (string) ($entries[0] ?? '') : '';

            return SmsResult::ok($id, (int) ($decoded['return']['cost'] ?? 0));
        }

        $error = \is_array($decoded) ? (string) ($decoded['return']['message'] ?? $body) : $body;

        return SmsResult::fail($error);
    }
}
