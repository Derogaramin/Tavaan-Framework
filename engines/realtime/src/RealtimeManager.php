<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Core\Contracts\Realtime\ShouldBroadcast;
use Tavaan\Engine\Realtime\Brokers\ArrayBroker;
use Tavaan\Engine\Realtime\Brokers\DatabaseBroker;
use Tavaan\Engine\Realtime\Brokers\RedisBroker;
use Tavaan\Engine\Realtime\Exceptions\RealtimeException;

/**
 * تَوان | Tavaan — Realtime
 * RealtimeManager — broadcast/publish/presence و مدیریت broker (DOC-045).
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class RealtimeManager
{
    /** @var array<string, BrokerInterface> */
    private array $brokers = [];

    /** @var array<string, \Closure> */
    private array $customBrokers = [];

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ChannelRegistry $channels,
        ?array $config = null,
    ) {
        $this->config = $config ?? ($container->bound('config')
            ? (array) $container->make('config')->get('realtime', [])
            : []);
    }

    // ─── Broker ──────────────────────────────────────────────────────────

    public function broker(?string $name = null): BrokerInterface
    {
        $name ??= (string) ($this->config['broker'] ?? 'array');
        if (isset($this->brokers[$name])) {
            return $this->brokers[$name];
        }

        return $this->brokers[$name] = $this->buildBroker($name);
    }

    public function extend(string $name, \Closure $factory): void
    {
        $this->customBrokers[$name] = $factory;
        unset($this->brokers[$name]);
    }

    private function buildBroker(string $name): BrokerInterface
    {
        if (isset($this->customBrokers[$name])) {
            $broker = ($this->customBrokers[$name])($this->container, $this->config);
            if (!$broker instanceof BrokerInterface) {
                throw RealtimeException::unknownBroker($name);
            }

            return $broker;
        }

        return match ($name) {
            'array' => new ArrayBroker((int) ($this->config['array']['cap'] ?? 1000)),
            'database' => $this->buildDatabaseBroker(),
            'redis' => $this->buildRedisBroker(),
            default => throw RealtimeException::unknownBroker($name),
        };
    }

    private function buildDatabaseBroker(): BrokerInterface
    {
        if (!$this->container->bound('db')) {
            throw RealtimeException::brokerUnavailable('database', 'db engine is not bound');
        }
        $connection = $this->container->make('db')->connection();

        return new DatabaseBroker(
            $connection,
            (string) ($this->config['database']['table'] ?? 'tvn_broadcast_events'),
            (int) ($this->config['database']['ttl'] ?? 3600),
        );
    }

    private function buildRedisBroker(): BrokerInterface
    {
        if (!$this->container->bound('queue')) {
            throw RealtimeException::brokerUnavailable('redis', 'queue engine (RedisClient) is not bound');
        }
        // RedisClient موتور queue با کانفیگ redis خود realtime
        (static fn () => new \Tavaan\Engine\Queue\Redis\RedisClient)()((array) ($this->config['redis'] ?? []));
        if (!$client->ping()) {
            // 🔒 fallback خودکار به database (بدون قطعی سرویس)
            return $this->buildDatabaseBroker();
        }

        return new RedisBroker($client, (int) ($this->config['redis']['cap'] ?? 1000));
    }

    // ─── Broadcast ───────────────────────────────────────────────────────

    /** ثبت الگوی کانال (routes/channels.php). */
    public function channel(string $pattern, \Closure $callback): void
    {
        $this->channels->channel($pattern, $callback);
    }

    public function channels(): ChannelRegistry
    {
        return $this->channels;
    }

    /**
     * انتشار یک رویداد ShouldBroadcast روی همهٔ کانال‌هایش.
     *
     * @return int تعداد انتشارها
     */
    public function broadcast(object $event): int
    {
        if (!$event instanceof ShouldBroadcast) {
            return 0;
        }
        if (\method_exists($event, 'broadcastWhen') && !$event->broadcastWhen()) {
            return 0;
        }

        $name = $event->broadcastAs() !== '' ? $event->broadcastAs() : $event::class;
        $payload = $event->broadcastWith();

        $count = 0;
        foreach ($event->broadcastOn() as $channel) {
            $this->publish((string) $channel, $name, $payload);
            $count++;
        }

        return $count;
    }

    /**
     * انتشار مستقیم روی یک کانال.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $channel, string $name, array $payload): void
    {
        $channel = $this->sanitizeChannel($channel);
        $this->broker()->publish($channel, $name, $payload);
    }

    /**
     * احراز کانال خصوصی برای یک کاربر (DOC-045 §8).
     *
     * @return bool|array نتیجهٔ callback (true یا دادهٔ presence)
     */
    public function authorize(object $user, string $channel): bool|array
    {
        $resolved = $this->channels->resolve($channel);
        if ($resolved === null) {
            // کانال ثبت‌نشده → فقط کانال‌های عمومی (بدون prefix خصوصی) مجازند
            return !$this->isPrivate($channel);
        }

        $params = $this->castParams($resolved['params']);
        $result = ($resolved['callback'])($user, ...$params);

        if (\is_array($result)) {
            return $result;   // presence payload
        }

        return (bool) $result;
    }

    public function isPrivate(string $channel): bool
    {
        return \str_starts_with($channel, 'private:') || \str_starts_with($channel, 'private.');
    }

    public function isPresence(string $channel): bool
    {
        return \str_starts_with($channel, 'presence:') || \str_starts_with($channel, 'presence.');
    }

    /**
     * امضای توکن عضویت کانال (HMAC با کلید اپ).
     *
     * @param array<string, mixed>|null $presence
     */
    public function sign(string $channel, object $user, ?array $presence = null): string
    {
        $userId = $this->userId($user);
        $data = \json_encode([
            'channel' => $channel,
            'user' => $userId,
            'presence' => $presence,
            'exp' => \time() + (int) ($this->config['auth_ttl'] ?? 3600),
        ], JSON_UNESCAPED_UNICODE);

        return \hash_hmac('sha256', (string) $data, $this->secret()) . ':' . \base64_encode((string) $data);
    }

    /** @return array{channel: string, user: string, presence: array<string, mixed>|null}|null */
    public function verify(string $token): ?array
    {
        $parts = \explode(':', $token, 2);
        if (\count($parts) !== 2) {
            return null;
        }
        [$mac, $b64] = $parts;
        $data = \base64_decode($b64, true);
        if ($data === false) {
            return null;
        }
        $expected = \hash_hmac('sha256', $data, $this->secret());
        if (!\hash_equals($expected, $mac)) {
            return null;
        }
        $decoded = \json_decode($data, true);
        if (!\is_array($decoded) || !isset($decoded['channel'], $decoded['user'])) {
            return null;
        }
        if ((int) ($decoded['exp'] ?? 0) < \time()) {
            return null;
        }

        return [
            'channel' => (string) $decoded['channel'],
            'user' => (string) $decoded['user'],
            'presence' => isset($decoded['presence']) && \is_array($decoded['presence']) ? $decoded['presence'] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    // ─── درونی ───────────────────────────────────────────────────────────

    private function sanitizeChannel(string $channel): string
    {
        $channel = \trim($channel);
        // 🔒 جلوگیری از Message injection — فقط کاراکترهای امن کانال
        $clean = (string) \preg_replace('/[^A-Za-z0-9._:\-\/]/', '', $channel);

        return \mb_substr($clean, 0, 200);
    }

    private function userId(object $user): string
    {
        if (\method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }
        if (isset($user->id)) {
            return (string) $user->id;
        }

        return \spl_object_hash($user);
    }

    private function secret(): string
    {
        $key = (string) ($this->config['auth_secret'] ?? '');
        if ($key !== '') {
            return $key;
        }
        if ($this->container->bound('config')) {
            $appKey = (string) $this->container->make('config')->get('app.key', '');
            if ($appKey !== '') {
                return $appKey;
            }
        }

        throw RealtimeException::for('realtime.auth_secret_missing');
    }

    /**
     * @param list<string> $params
     *
     * @return list<string|int>
     */
    private function castParams(array $params): array
    {
        return \array_map(static fn (string $p): string|int => \ctype_digit($p) ? (int) $p : $p, $params);
    }
}
