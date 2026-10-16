<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification\Channels;

use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\HttpClient\Factory as HttpFactory;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Messages\WebhookMessage;
use Tavaan\Engine\Notification\Notification;

/**
 * تَوان | Tavaan — Notification
 * WebhookChannel — POST به سرویس خارجی با موتور HttpClient (DOC-066 §2).
 *
 * 🔒 SSRF Guard موتور HttpClient روی URL فعال است (DOC-066 §7).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class WebhookChannel implements ChannelInterface
{
    public function __construct(private readonly ?HttpFactory $http = null)
    {
    }

    public function send(Notifiable $notifiable, mixed $message, Notification $notification): mixed
    {
        if ($this->http === null) {
            throw NotificationException::channelUnavailable('webhook');
        }

        if (!$message instanceof WebhookMessage) {
            throw NotificationException::invalidMessage('webhook', WebhookMessage::class);
        }

        $payload = $message->payload + [
            'notification_id' => $notification->id,
            'type' => $notification::class,
        ];

        $request = $this->http->newRequest()->asJson()->timeout(15);
        if ($message->getHeaders() !== []) {
            $request->withHeaders($message->getHeaders());
        }

        $response = $request->post($message->url, $payload);

        if ($response->failed()) {
            throw NotificationException::channelUnavailable('webhook: status ' . $response->status());
        }

        return $response;
    }

    public function name(): string
    {
        return 'webhook';
    }
}
