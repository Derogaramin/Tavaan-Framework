<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\HttpClient\Factory as HttpFactory;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Messages\TelegramMessage;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * TelegramChannel — کانال پیام‌رسان‌ها با Bot API مشترک (DOC-066 §2).
 *
 * یک کلاس برای هر سه پیام‌رسان — فقط endpoint/token متفاوت:
 * - telegram: https://api.telegram.org
 * - bale:     https://tapi.bale.ai
 * - eitaa:    https://bot.eitaa.com (قابل override با config)
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class TelegramChannel implements ChannelInterface
{
    /**
     * @param array<string, mixed> $config {base_url, token}
     */
    public function __construct(
        private readonly string $channelName,
        private readonly array $config = [],
        private readonly ?HttpFactory $http = null,
    ) {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->http === null) {
            throw NotificationException::channelUnavailable($this->channelName);
        }

        if (!$message instanceof TelegramMessage) {
            throw NotificationException::invalidMessage($this->channelName, TelegramMessage::class);
        }

        $chatId = $notifiable->routeNotificationFor($this->channelName, $notification);
        if (!\is_string($chatId) && !\is_int($chatId)) {
            throw NotificationException::missingRoute($this->channelName, $notifiable);
        }

        $token = (string) ($this->config['token'] ?? '');
        if ($token === '') {
            throw NotificationException::channelUnavailable($this->channelName . ': token missing');
        }

        $baseUrl = \rtrim((string) ($this->config['base_url'] ?? $this->defaultBaseUrl()), '/');

        $payload = [
            'chat_id' => $chatId,
            'text' => $message->content,
            'parse_mode' => $message->getParseMode(),
        ];
        $markup = $message->replyMarkup();
        if ($markup !== null) {
            $payload['reply_markup'] = \json_encode($markup, \JSON_UNESCAPED_UNICODE);
        }

        $response = $this->http->newRequest()
            ->asJson()
            ->timeout(15)
            ->post($baseUrl . '/bot' . $token . '/sendMessage', $payload);

        if ($response->failed()) {
            throw NotificationException::channelUnavailable($this->channelName . ': status ' . $response->status());
        }

        $decoded = $response->json();

        return \is_array($decoded) ? (string) ($decoded['result']['message_id'] ?? '') : '';
    }

    public function name(): string
    {
        return $this->channelName;
    }

    private function defaultBaseUrl(): string
    {
        return match ($this->channelName) {
            'bale' => 'https://tapi.bale.ai',
            'eitaa' => 'https://bot.eitaa.com/api',
            default => 'https://api.telegram.org',
        };
    }
}
