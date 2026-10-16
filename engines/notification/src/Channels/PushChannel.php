<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\HttpClient\Factory as HttpFactory;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * PushChannel — push موبایل با FCM HTTP v1-like adapter (DOC-066 §2).
 *
 * پیام `toPush()` یک آرایهٔ `{title, body, data, token?}` است؛
 * token از `routeNotificationFor('push')` خوانده می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class PushChannel implements ChannelInterface
{
    /**
     * @param array<string, mixed> $config {endpoint, server_key, project_id}
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?HttpFactory $http = null,
    ) {
    }

    /** @param mixed $message */
    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->http === null) {
            throw NotificationException::channelUnavailable('push');
        }

        $payload = \is_array($message) ? $message : (array) $message;
        $token = $payload['token'] ?? $notifiable->routeNotificationFor('push', $notification);

        if (!\is_string($token) || $token === '') {
            throw NotificationException::missingRoute('push', $notifiable);
        }

        $endpoint = (string) ($this->config['endpoint'] ?? 'https://fcm.googleapis.com/fcm/send');
        $serverKey = (string) ($this->config['server_key'] ?? '');

        $response = $this->http->newRequest()
            ->asJson()
            ->timeout(15)
            ->withHeaders($serverKey !== '' ? ['Authorization' => 'key=' . $serverKey] : [])
            ->post($endpoint, [
                'to' => $token,
                'notification' => [
                    'title' => (string) ($payload['title'] ?? ''),
                    'body' => (string) ($payload['body'] ?? ''),
                ],
                'data' => (array) ($payload['data'] ?? []) + ['notification_id' => $notification->id],
            ]);

        if ($response->failed()) {
            throw NotificationException::channelUnavailable('push: status ' . $response->status());
        }

        return $response;
    }

    public function name(): string
    {
        return 'push';
    }
}
