<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Notification\Notifiable;
use Tavaan\Engine\Notification\Channels\BroadcastChannel;
use Tavaan\Engine\Notification\Channels\ChannelInterface;
use Tavaan\Engine\Notification\Channels\DatabaseChannel;
use Tavaan\Engine\Notification\Channels\MailChannel;
use Tavaan\Engine\Notification\Channels\PushChannel;
use Tavaan\Engine\Notification\Channels\SmsChannel;
use Tavaan\Engine\Notification\Channels\TelegramChannel;
use Tavaan\Engine\Notification\Channels\WebhookChannel;
use Tavaan\Engine\Notification\Events\NotificationFailed;
use Tavaan\Engine\Notification\Events\NotificationSending;
use Tavaan\Engine\Notification\Events\NotificationSent;
use Tavaan\Engine\Notification\Exceptions\NotificationException;
use Tavaan\Engine\Notification\Jobs\SendQueuedNotification;
use Tavaan\Engine\Notification\Sms\LogSmsGateway;
use Tavaan\Engine\Notification\Sms\SmsGatewayInterface;

/**
 * تَوان | Tavaan — Notification
 * NotificationSender — هماهنگ‌کنندهٔ ارسال چندکاناله (Facade: Notification؛ DOC-066 §3).
 *
 * ```php
 * Notification::send($users, new OrderShipped($order));
 * Notification::sendNow($user, new OrderShipped($order));   // sync
 * Notification::route('mail', $email)->notify(new Welcome()); // بدون مدل
 * $user->notify(new OrderShipped($order));                    // trait RoutesNotifications
 * ```
 *
 * 🔒 Rate Limit هر کاربر (جلوگیری از SMS Bombing — DOC-066 §7).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class NotificationSender
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    /** @var array<string, \Closure(): ChannelInterface> */
    private array $channelFactories = [];

    /** @var array<string, \Closure(): SmsGatewayInterface> */
    private array $smsGatewayFactories = [];

    private ?SmsGatewayInterface $smsGateway = null;

    private bool $faking = false;

    /** @var list<array{notifiable: Notifiable, notification: Notification, channels: list<string>}> */
    private array $sent = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private readonly ?ConfigRepository $config = null,
        private readonly ?DispatcherInterface $events = null,
        private readonly ?CacheRepository $cache = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    // ─── API اصلی (DOC-066 §3) ────────────────────────────────────────────

    /**
     * ارسال به یک/چند نوتیفای‌شونده (async اگر notification صف‌روند باشد).
     *
     * @param Notifiable|iterable<Notifiable> $notifiables
     *
     * @return list<array{notifiable: Notifiable, status: string, channels: list<string>}>
     */
    public function send(Notifiable|iterable $notifiables, Notification $notification): array
    {
        $results = [];
        foreach ($this->normalizeNotifiables($notifiables) as $notifiable) {
            if ($this->shouldQueue($notification)) {
                $this->queueNotification($notifiable, $notification);
                $results[] = ['notifiable' => $notifiable, 'status' => 'queued', 'channels' => []];
                continue;
            }

            $channels = $this->sendNow($notifiable, $notification);
            $results[] = ['notifiable' => $notifiable, 'status' => 'sent', 'channels' => $channels];
        }

        return $results;
    }

    /**
     * ارسال همزمان (بدون صف).
     *
     * @return list<string> کانال‌های ارسال‌شده
     */
    public function sendNow(Notifiable $notifiable, Notification $notification): array
    {
        if ($this->faking) {
            $channels = $this->channelsFor($notifiable, $notification);
            $this->sent[] = ['notifiable' => $notifiable, 'notification' => $notification, 'channels' => $channels];

            return $channels;
        }

        $delivered = [];

        foreach ($this->channelsFor($notifiable, $notification) as $channelName) {
            // 🔒 Rate Limit (DOC-066 §7)
            if (!$this->rateLimitAllows($channelName, $notifiable)) {
                $this->logger?->warning('notification.rate_limited', [
                    'channel' => $channelName,
                    'notifiable' => $notifiable::class,
                ]);
                $this->events?->dispatch(new NotificationFailed($notifiable, $notification, $channelName, 'rate_limited'));
                continue;
            }

            try {
                $message = $this->buildMessage($channelName, $notifiable, $notification);
                if ($message === null) {
                    continue;   // کانال برای این notification تعریف نشده
                }

                $this->events?->dispatch(new NotificationSending($notifiable, $notification, $channelName));

                $result = $this->channel($channelName)->send($notifiable, $message, $notification);

                $delivered[] = $channelName;
                $this->events?->dispatch(new NotificationSent($notifiable, $notification, $channelName, $result));
            } catch (\Throwable $e) {
                // 🔒 شکست یک کانال، بقیه را متوقف نمی‌کند
                $this->logger?->error('notification.channel_failed', [
                    'channel' => $channelName,
                    'notification' => $notification::class,
                    'error' => $e->getMessage(),
                ]);
                $this->events?->dispatch(new NotificationFailed($notifiable, $notification, $channelName, $e->getMessage()));
            }
        }

        $this->sent[] = ['notifiable' => $notifiable, 'notification' => $notification, 'channels' => $delivered];

        return $delivered;
    }

    /** آگاه‌سازی بدون مدل — `Notification::route('mail', $email)->notify(...)`. */
    public function route(string $channel, mixed $route): AnonymousNotifiable
    {
        return (new AnonymousNotifiable())->route($channel, $route);
    }

    // ─── کانال‌ها ─────────────────────────────────────────────────────────

    public function channel(string $name): ChannelInterface
    {
        return $this->channels[$name] ??= $this->resolveChannel($name);
    }

    /** ثبت کانال سفارشی. */
    public function extend(string $name, \Closure $factory): void
    {
        $this->channelFactories[$name] = $factory;
        unset($this->channels[$name]);
    }

    /** override درگاه پیامک (تست/درایور سفارشی). */
    public function useSmsGateway(SmsGatewayInterface $gateway): void
    {
        $this->smsGateway = $gateway;
        unset($this->channels['sms']);
    }

    public function smsGateway(): SmsGatewayInterface
    {
        return $this->smsGateway ??= $this->resolveSmsGateway();
    }

    /** کانال In-App (برای unread/markAsRead — DOC-066 §4). */
    public function database(): DatabaseChannel
    {
        $channel = $this->channel('database');

        return $channel instanceof DatabaseChannel
            ? $channel
            : throw NotificationException::channelUnavailable('database');
    }

    private function resolveChannel(string $name): ChannelInterface
    {
        if (isset($this->channelFactories[$name])) {
            return ($this->channelFactories[$name])();
        }

        return match ($name) {
            'mail' => new MailChannel($this->bound('mailer') ? $this->container?->make('mailer') : null),
            'sms' => new SmsChannel($this->smsGateway()),
            'database' => new DatabaseChannel(
                $this->container !== null && $this->container->bound(ConnectionInterface::class)
                    ? $this->container->make(ConnectionInterface::class)
                    : null,
                (string) ($this->config?->get('notification.database.table', 'tvn_notifications') ?? 'tvn_notifications'),
            ),
            'webhook' => new WebhookChannel($this->httpFactory()),
            'telegram', 'bale', 'eitaa' => new TelegramChannel(
                $name,
                (array) ($this->config?->get('notification.' . $name, []) ?? []),
                $this->httpFactory(),
            ),
            'broadcast' => new BroadcastChannel($this->events),
            'push' => new PushChannel((array) ($this->config?->get('notification.push', []) ?? []), $this->httpFactory()),
            default => throw NotificationException::unknownChannel($name),
        };
    }

    private function resolveSmsGateway(): SmsGatewayInterface
    {
        $driver = (string) ($this->config?->get('notification.sms.driver', 'log') ?? 'log');
        $config = (array) ($this->config?->get('notification.sms.gateways.' . $driver, []) ?? []);
        $http = $this->httpFactory();

        return match ($driver) {
            'kavenegar' => new Sms\Gateways\KavenegarGateway($config, $http),
            'melipayamak' => new Sms\Gateways\MelipayamakGateway($config, $http),
            'ghasedak' => new Sms\Gateways\GhasedakGateway($config, $http),
            'smsir' => new Sms\Gateways\SmsIrGateway($config, $http),
            'farazsms' => new Sms\Gateways\FarazSmsGateway($config, $http),
            'log' => new LogSmsGateway($this->logger),
            default => isset($this->smsGatewayFactories[$driver])
                ? ($this->smsGatewayFactories[$driver])()
                : throw NotificationException::unknownChannel('sms.' . $driver),
        };
    }

    private function httpFactory(): ?\Tavaan\Engine\HttpClient\Factory
    {
        if ($this->container === null || !$this->container->bound('http.client')) {
            return null;
        }

        $factory = $this->container->make('http.client');

        return $factory instanceof \Tavaan\Engine\HttpClient\Factory ? $factory : null;
    }

    private function bound(string $abstract): bool
    {
        return $this->container !== null && $this->container->bound($abstract);
    }

    // ─── Fake / Assertion (تست؛ DOC-066 §8 T-07) ─────────────────────────

    public function fake(): self
    {
        $this->faking = true;
        $this->sent = [];

        return $this;
    }

    public function isFaking(): bool
    {
        return $this->faking;
    }

    /**
     * @param class-string<Notification> $notificationClass
     * @param callable(Notifiable, Notification, list<string>): bool|null $callback
     */
    public function assertSentTo(Notifiable $notifiable, string $notificationClass, ?callable $callback = null): void
    {
        foreach ($this->sent as $entry) {
            if (!$entry['notification'] instanceof $notificationClass) {
                continue;
            }
            if (!$this->sameNotifiable($entry['notifiable'], $notifiable)) {
                continue;
            }
            if ($callback === null || $callback($entry['notifiable'], $entry['notification'], $entry['channels'])) {
                return;
            }
        }

        throw NotificationException::assertionFailed($notificationClass . ' → ' . $notifiable::class);
    }

    /** @param class-string<Notification> $notificationClass */
    public function assertNotSentTo(Notifiable $notifiable, string $notificationClass): void
    {
        foreach ($this->sent as $entry) {
            if ($entry['notification'] instanceof $notificationClass && $this->sameNotifiable($entry['notifiable'], $notifiable)) {
                throw NotificationException::assertionFailed('!' . $notificationClass);
            }
        }
    }

    public function assertSentCount(int $count): void
    {
        if (\count($this->sent) !== $count) {
            throw NotificationException::assertionFailed('count=' . $count . ' actual=' . \count($this->sent));
        }
    }

    /** @return list<array{notifiable: Notifiable, notification: Notification, channels: list<string>}> */
    public function sent(): array
    {
        return $this->sent;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function channelsFor(Notifiable $notifiable, Notification $notification): array
    {
        $channels = $notification->via($notifiable);
        $enabled = (array) ($this->config?->get('notification.channels', []) ?? []);

        if ($enabled !== []) {
            $channels = \array_values(\array_filter($channels, static fn (string $c): bool => \in_array($c, $enabled, true)));
        }

        return $channels;
    }

    private function buildMessage(string $channel, Notifiable $notifiable, Notification $notification): mixed
    {
        $method = 'to' . \ucfirst($channel);

        return \method_exists($notification, $method) ? $notification->{$method}($notifiable) : null;
    }

    private function shouldQueue(Notification $notification): bool
    {
        $configWantsQueue = (bool) ($this->config?->get('notification.queue', true) ?? true);

        return $configWantsQueue
            && $notification instanceof \Tavaan\Core\Contracts\Queue\ShouldQueue
            && $this->bound('queue');
    }

    private function queueNotification(Notifiable $notifiable, Notification $notification): void
    {
        $queue = $this->container?->make('queue');
        $queue?->push(new SendQueuedNotification($notifiable, $notification));
    }

    /** 🔒 Rate Limit per user per hour (DOC-066 §7). */
    private function rateLimitAllows(string $channel, Notifiable $notifiable): bool
    {
        if ($this->cache === null) {
            return true;
        }

        $max = (int) ($this->config?->get('notification.rate_limit.per_user_per_hour', 20) ?? 20);
        if ($max <= 0) {
            return true;    // ۰ = غیرفعال
        }

        $key = 'tvn:notification:rate:' . $channel . ':' . $notifiable::class . ':' . $notifiable->getNotificationKey();

        if ($this->cache->add($key, 1, 3600)) {
            return true;
        }

        $current = $this->cache->increment($key);

        return $current === false || $current <= $max;
    }

    /** @param Notifiable|iterable<Notifiable> $notifiables @return list<Notifiable> */
    private function normalizeNotifiables(Notifiable|iterable $notifiables): array
    {
        if ($notifiables instanceof Notifiable) {
            return [$notifiables];
        }

        $out = [];
        foreach ($notifiables as $notifiable) {
            if ($notifiable instanceof Notifiable) {
                $out[] = $notifiable;
            }
        }

        return $out;
    }

    private function sameNotifiable(Notifiable $a, Notifiable $b): bool
    {
        return $a::class === $b::class
            && (string) $a->getNotificationKey() === (string) $b->getNotificationKey();
    }
}
