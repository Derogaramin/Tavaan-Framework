<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\Tests\Feature;

require_once __DIR__ . '/../Fixtures/RealtimeFixtures.php';

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\StreamedResponse;
use Tavaan\Engine\Realtime\BroadcastingAuthController;
use Tavaan\Engine\Realtime\Brokers\ArrayBroker;
use Tavaan\Engine\Realtime\Brokers\DatabaseBroker;
use Tavaan\Engine\Realtime\Brokers\RedisBroker;
use Tavaan\Engine\Realtime\ChannelRegistry;
use Tavaan\Engine\Realtime\PresenceManager;
use Tavaan\Engine\Realtime\RealtimeManager;
use Tavaan\Engine\Realtime\Sse\SseStreamer;
use Tavaan\Engine\Realtime\Tests\Fixtures\FakeRealtimeRedis;
use Tavaan\Engine\Realtime\Tests\Fixtures\OrderStatusChanged;
use Tavaan\Engine\Realtime\Tests\Fixtures\RUser;
use Tavaan\Engine\Realtime\WebSocket\WebSocketFrame;
use Tavaan\Engine\Realtime\WebSocket\WebSocketProtocolException;
use Tavaan\Engine\Realtime\WebSocket\WebSocketServer;
use Tavaan\Support\Time\Clock;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Realtime — DOC-045 §11 (T-01..T-08).
 */
final class RealtimeTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        Clock::setTestNow(null);
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('cache.default', 'array');
        $this->app->make('config')->set('realtime.broker', 'array');
    }

    protected function tearDown(): void
    {
        Clock::setTestNow(null);
    }

    private function manager(array $config = []): RealtimeManager
    {
        return new RealtimeManager($this->app, new ChannelRegistry(), [
            'broker' => 'array',
            'auth_ttl' => 3600,
            ...$config,
        ]);
    }

    // ─── T-01: SSE stream + event format ─────────────────────────────────

    public function testSseEventFormat(): void
    {
        $out = SseStreamer::formatEvent('order.status', ['status' => 'paid', 'fa' => 'پرداخت شد'], 42);

        $this->assertSame("id: 42\nevent: order.status\ndata: {\"status\":\"paid\",\"fa\":\"پرداخت شد\"}\n\n", $out);
    }

    public function testSseEventNameSanitizedAgainstInjection(): void
    {
        // 🔒 نام رویداد با \r\n نباید بتواند پروتکل بشکند
        $out = SseStreamer::formatEvent("evil\r\ndata: injected", ['x' => 1]);
        $this->assertFalse(\str_contains($out, "\r"));
        $this->assertStringContainsString('event: evil.data. injected', $out);
        $this->assertSame(1, \substr_count($out, 'data: '));
    }

    public function testSseRetryAndCommentFormat(): void
    {
        $this->assertSame("retry: 3000\n\n", SseStreamer::formatRetry(3000));
        $this->assertSame(": keep-alive\n\n", SseStreamer::formatComment('keep-alive'));
    }

    public function testSseStreamedResponseContainsPublishedEvents(): void
    {
        $manager = $this->manager();
        $streamer = new SseStreamer($manager, ['heartbeat' => 15, 'retry_ms' => 3000]);

        $response = $streamer->response(['orders.42'], maxSeconds: 1);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/event-stream', (string) $response->header('Content-Type'));
        $this->assertSame('no', $response->header('X-Accel-Buffering'));

        // رویداد پیش از اجرای body منتشر می‌شود (startId در response() ثبت شده)
        $manager->publish('orders.42', 'order.status', ['status' => 'paid']);

        $body = $response->body();
        $this->assertStringContainsString('retry: 3000', $body);
        $this->assertStringContainsString('event: order.status', $body);
        $this->assertStringContainsString('"status":"paid"', $body);
    }

    public function testSseHeartbeatEmittedWhenDue(): void
    {
        $manager = $this->manager();
        $streamer = new SseStreamer($manager, []);

        \ob_start();
        // heartbeat=0 → در پاس bounded خط keep-alive صادر می‌شود
        $streamer->streamTo(['orders.1'], $manager->broker(), $manager->broker()->lastId(), 0, 3000, 1);
        $body = (string) \ob_get_clean();

        $this->assertStringContainsString(': keep-alive', $body);
    }

    public function testSseEndpointRoute(): void
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        // بدون channels → 400
        $bad = $kernel->handle(Request::create('http://localhost/events/stream'));
        $this->assertSame(400, $bad->statusCode());

        // کانال خصوصی → 403
        $forbidden = $kernel->handle(Request::create('http://localhost/events/stream?channels=private:orders.42'));
        $this->assertSame(403, $forbidden->statusCode());

        // کانال عمومی → 200 + event-stream
        $ok = $kernel->handle(Request::create('http://localhost/events/stream?channels=public.news&max_seconds=1'));
        $this->assertSame(200, $ok->statusCode());
        $this->assertStringContainsString('text/event-stream', (string) $ok->header('Content-Type'));
    }

    // ─── T-02: Broadcast از رویداد ShouldBroadcast ───────────────────────

    public function testDispatchBroadcastsShouldBroadcastEvent(): void
    {
        $this->app->make('events')->dispatch(new OrderStatusChanged(42, 'paid'));

        /** @var RealtimeManager $manager */
        $manager = $this->app->make('realtime');
        $events = $manager->broker()->poll(['orders.42'], 0, 0);

        $this->assertCount(1, $events);
        $this->assertSame('order.status', $events[0]['name']);
        $this->assertSame('paid', $events[0]['payload']['status']);
        $this->assertSame(42, $events[0]['payload']['order_id']);
    }

    public function testBroadcastWhenFalseSkipsPublish(): void
    {
        $event = new OrderStatusChanged(7, 'cancelled');
        $event->broadcast = false;
        $this->app->make('events')->dispatch($event);

        /** @var RealtimeManager $manager */
        $manager = $this->app->make('realtime');
        $this->assertSame([], $manager->broker()->poll(['orders.7'], 0, 0));
    }

    public function testChannelNameSanitized(): void
    {
        $manager = $this->manager();
        $broker = new ArrayBroker();
        $manager->extend('array', static fn (): ArrayBroker => $broker);

        $manager->publish("orders.42\r\nINJECTED", 'x', []);
        $events = $broker->poll(['orders.42INJECTED'], 0, 0);
        $this->assertCount(1, $events);   // کاراکترهای خطرناک حذف شدند
    }

    // ─── T-03: احراز کانال خصوصی ─────────────────────────────────────────

    public function testChannelRegistryResolvesPatterns(): void
    {
        $registry = new ChannelRegistry();
        $registry->channel('orders.{orderId}', static fn (): bool => true);
        $registry->channel('chat.*', static fn (): bool => true);
        $registry->channel('public.news', static fn (): bool => true);

        $resolved = $registry->resolve('orders.42');
        $this->assertNotNull($resolved);
        $this->assertSame(['42'], $resolved['params']);

        $wild = $registry->resolve('chat.room-1');
        $this->assertNotNull($wild);

        $this->assertSame('public.news', $registry->resolve('public.news')['pattern']);
        $this->assertNull($registry->resolve('orders.42.extra'));
        $this->assertNull($registry->resolve('unknown.channel'));
    }

    public function testPrivateChannelNormalization(): void
    {
        $registry = new ChannelRegistry();
        $registry->channel('private:orders.{id}', static fn (): bool => true);
        // Laravel-style private.orders.42 → private:orders.42
        $this->assertNotNull($registry->resolve('private.orders.42'));
    }

    public function testAuthorizeUsesChannelCallback(): void
    {
        $manager = $this->manager();
        $manager->channel('private:orders.{orderId}', static fn (RUser $user, int $orderId): bool => $user->can('view-order-' . $orderId));

        $allowed = new RUser(1, 'ali', ['view-order-42']);
        $denied = new RUser(2, 'reza');

        $this->assertTrue($manager->authorize($allowed, 'private:orders.42'));
        $this->assertFalse($manager->authorize($denied, 'private:orders.42'));

        // کانال خصوصی ثبت‌نشده → رد
        $this->assertFalse($manager->authorize($allowed, 'private:secret.1'));
        // کانال عمومی ثبت‌نشده → مجاز
        $this->assertTrue($manager->authorize($allowed, 'public.news'));
    }

    public function testBroadcastingAuthEndpoint(): void
    {
        /** @var RealtimeManager $manager */
        $manager = $this->app->make('realtime');
        $manager->channel('private:orders.{orderId}', static fn (object $user, int $orderId): bool => $orderId === 42);

        /** @var BroadcastingAuthController $controller */
        $controller = $this->app->make(BroadcastingAuthController::class);

        // کاربر احراز‌شده + کانال مجاز → توکن
        $request = Request::create('http://localhost/broadcasting/auth', 'POST', ['channel_name' => 'private:orders.42'])
            ->withAttribute('user', new RUser(5));
        $response = $controller->auth($request);
        $this->assertSame(200, $response->statusCode());

        $decoded = \json_decode($response->body(), true);
        $this->assertArrayHasKey('auth', $decoded);

        $verified = $manager->verify((string) $decoded['auth']);
        $this->assertNotNull($verified);
        $this->assertSame('private:orders.42', $verified['channel']);
        $this->assertSame('5', $verified['user']);

        // کاربر ناشناس → 403
        $anon = $controller->auth(Request::create('http://localhost/broadcasting/auth', 'POST', ['channel_name' => 'private:orders.42']));
        $this->assertSame(403, $anon->statusCode());

        // کانال غیرمجاز → 403
        $denied = $controller->auth(
            Request::create('http://localhost/broadcasting/auth', 'POST', ['channel_name' => 'private:orders.99'])
                ->withAttribute('user', new RUser(5)),
        );
        $this->assertSame(403, $denied->statusCode());
    }

    public function testSignedTokenRejectsTampering(): void
    {
        $manager = $this->manager();
        $token = $manager->sign('private:orders.42', new RUser(1));

        $this->assertNotNull($manager->verify($token));
        $this->assertNull($manager->verify($token . 'x'));                 // tamper
        $this->assertNull($manager->verify('garbage'));
        [$mac, $b64] = \explode(':', $token, 2);
        $forged = $mac . ':' . \base64_encode('{"channel":"private:all","user":"1","exp":9999999999}');
        $this->assertNull($manager->verify($forged));                      // payload جعلی با mac قبلی
    }

    // ─── T-04/T-05: WebSocket handshake + frames ─────────────────────────

    public function testWebSocketAcceptKeyRfcVector(): void
    {
        // RFC 6455 §1.3 نمونهٔ رسمی
        $this->assertSame(
            's3pPLMBiTxaQ9kYGzzhZRbK+xOo=',
            WebSocketFrame::acceptKey('dGhlIHNhbXBsZSBub25jZQ=='),
        );
    }

    public function testHandshakeResponse(): void
    {
        $server = new WebSocketServer($this->manager(), []);
        $head = "GET /ws HTTP/1.1\r\nHost: localhost:8081\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\n\r\n";

        $response = $server->handshakeResponse($head);
        $this->assertNotNull($response);
        $this->assertStringContainsString('HTTP/1.1 101 Switching Protocols', (string) $response);
        $this->assertStringContainsString('Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', (string) $response);

        // سرِ ناقص → null
        $this->assertNull($server->handshakeResponse("GET /ws HTTP/1.1\r\nUpgrade: websocket\r\n"));

        // بدون Upgrade → 400
        $bad = "GET /ws HTTP/1.1\r\nHost: x\r\n\r\n";
        $this->assertStringContainsString('400', (string) $server->handshakeResponse($bad));
    }

    public function testHandshakeOriginCheck(): void
    {
        $server = new WebSocketServer($this->manager(), ['allowed_origins' => ['https://app.example']]);
        $make = static fn (string $origin): string => "GET /ws HTTP/1.1\r\nHost: x\r\nUpgrade: websocket\r\n"
            . 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' . "\r\n"
            . ($origin === '' ? '' : 'Origin: ' . $origin . "\r\n") . "\r\n";

        $this->assertStringContainsString('101', (string) $server->handshakeResponse($make('https://app.example')));
        // 🔒 Origin ناشناس → 403
        $this->assertStringContainsString('403', (string) $server->handshakeResponse($make('https://evil.com')));
        // 🔒 Origin غایب وقتی لیست فعال است → 403
        $this->assertStringContainsString('403', (string) $server->handshakeResponse($make('')));
    }

    public function testFrameRoundTripMasked(): void
    {
        $payload = 'سلام WebSocket!';
        // client→server: masked
        $raw = WebSocketFrame::encode($payload, WebSocketFrame::OP_TEXT, true, true);
        $frame = WebSocketFrame::decode($raw, true);

        $this->assertNotNull($frame);
        $this->assertTrue($frame->fin);
        $this->assertSame(WebSocketFrame::OP_TEXT, $frame->opcode);
        $this->assertSame($payload, $frame->payload);
        $this->assertSame(\strlen($raw), $frame->consumed);

        // 🔒 فریم بدون mask از client → خطای پروتکل
        $unmasked = WebSocketFrame::encode($payload);
        try {
            WebSocketFrame::decode($unmasked, true);
            $this->fail('باید WebSocketProtocolException پرتاب می‌شد');
        } catch (WebSocketProtocolException $e) {
            $this->assertSame('realtime.invalid_frame', $e->messageId);
        }
        // اما server→client بدون mask پذیرفته می‌شود
        $this->assertSame($payload, WebSocketFrame::decode($unmasked, false)?->payload);
    }

    public function testFramePayloadLengthForms(): void
    {
        foreach ([125, 126, 65535, 65536] as $size) {
            $payload = \str_repeat('a', (int) $size);
            $raw = WebSocketFrame::encode($payload, WebSocketFrame::OP_TEXT, true, true);
            $frame = WebSocketFrame::decode($raw, true);
            $this->assertSame($payload, $frame?->payload, 'size=' . $size);
        }
    }

    public function testFrameIncompleteBufferReturnsNull(): void
    {
        $raw = WebSocketFrame::encode('hello world', WebSocketFrame::OP_TEXT, true, true);
        $this->assertNull(WebSocketFrame::decode(\substr($raw, 0, 1), true));
        $this->assertNull(WebSocketFrame::decode(\substr($raw, 0, \strlen($raw) - 1), true));
    }

    public function testControlFrames(): void
    {
        $ping = WebSocketFrame::encode('beat', WebSocketFrame::OP_PING, true, true);
        $frame = WebSocketFrame::decode($ping, true);
        $this->assertNotNull($frame);
        $this->assertTrue($frame->isPing());
        $this->assertSame('beat', $frame->payload);

        $close = WebSocketFrame::close(1001, 'going away');
        $closeFrame = WebSocketFrame::decode($close, false);
        $this->assertNotNull($closeFrame);
        $this->assertTrue($closeFrame->isClose());
        $this->assertSame(1001, $closeFrame->closeStatus()['code']);
        $this->assertSame('going away', $closeFrame->closeStatus()['reason']);
    }

    // ─── WebSocket server با socket pair ─────────────────────────────────

    public function testWebSocketServerSubscribeAndFanOut(): void
    {
        if (!\function_exists('stream_socket_pair') || \PHP_OS_FAMILY === 'Windows') {
            // 🔧 محیط بدون UNIX socket یا ویندوز (stream_socket_pair → خطای 10042) → skip
            $this->assertTrue(true);

            return;
        }

        $manager = $this->manager();
        $broker = new ArrayBroker();
        $manager->extend('array', static fn (): ArrayBroker => $broker);
        // کانال خصوصی با callback موفق
        $manager->channel('private:orders.{orderId}', static fn (): bool => true);

        $server = new WebSocketServer($manager, []);
        $server->withBroker($broker);

        [$client, $serverSide] = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertTrue(\is_resource($client) && \is_resource($serverSide));

        $server->attachClient($serverSide, "GET /ws?channels=public.news HTTP/1.1\r\n\r\n");
        $this->assertSame(1, $server->clientCount());

        // subscribe: عمومی + خصوصی با توکن معتبر + خصوصی بدون توکن
        $user = new RUser(9);
        $token = $manager->sign('private:orders.42', $user);
        $subscribe = (string) \json_encode([
            'event' => 'subscribe',
            'channels' => ['public.news', 'private:orders.42', 'private:secret.1'],
            'token' => $token,
        ]);
        \fwrite($client, WebSocketFrame::encode($subscribe, WebSocketFrame::OP_TEXT, true, true));

        $server->tick();

        $raw = (string) \fread($client, 65535);
        $frame = WebSocketFrame::decode($raw, false);
        $this->assertNotNull($frame);
        $response = \json_decode($frame->payload, true);
        $this->assertSame('subscribed', $response['event']);
        // public.news از قبل عضو بود → در granted تکرار نمی‌شود
        $this->assertContains('private:orders.42', $response['channels']);
        // 🔒 خصوصی بدون توکن → رد
        $this->assertFalse(\in_array('private:secret.1', $response['channels'], true));

        // fan-out: انتشار روی دو کانال عضو‌شده
        $broker->publish('public.news', 'news.item', ['title' => 'خبر ۱']);
        $broker->publish('private:orders.42', 'order.status', ['status' => 'paid']);
        $broker->publish('other.channel', 'ignored', []);

        $server->tick();
        $raw2 = (string) \fread($client, 65535);

        $texts = [];
        $offset = 0;
        while ($offset < \strlen($raw2)) {
            $f = WebSocketFrame::decode(\substr($raw2, $offset), false);
            if ($f === null) {
                break;
            }
            $texts[] = $f->payload;
            $offset += $f->consumed;
        }
        $this->assertCount(2, $texts);
        $first = \json_decode($texts[0], true);
        $this->assertSame('news.item', $first['event']);
        $this->assertSame('public.news', $first['channel']);
        $second = \json_decode($texts[1], true);
        $this->assertSame('order.status', $second['event']);

        // ping → pong
        \fwrite($client, WebSocketFrame::encode('', WebSocketFrame::OP_PING, true, true));
        $server->tick();
        $raw3 = (string) \fread($client, 65535);
        $pong = WebSocketFrame::decode($raw3, false);
        $this->assertSame(WebSocketFrame::OP_PONG, $pong?->opcode);

        // close → disconnect
        \fwrite($client, WebSocketFrame::close(1000));
        $server->tick();
        $this->assertSame(0, $server->clientCount());

        @\fclose($client);
    }

    // ─── T-07: Broker fan-out (Database + Redis) ─────────────────────────

    public function testDatabaseBrokerRoundTrip(): void
    {
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app->make('schema')->create('tvn_broadcast_events', function (Blueprint $t): void {
            $t->id();
            $t->string('channel', 200)->index();
            $t->string('name', 190);
            $t->text('payload');
            $t->dateTime('created_at', 3)->index();
        });

        $connection = $this->app->make('db')->connection();
        $broker = new DatabaseBroker($connection, 'tvn_broadcast_events', 3600);

        $this->assertSame(0, $broker->lastId());
        $broker->publish('orders.1', 'order.status', ['s' => 'new']);
        $broker->publish('orders.2', 'order.status', ['s' => 'paid']);
        $broker->publish('orders.1', 'order.note', ['n' => 'سلام']);

        $all = $broker->poll(['orders.1'], 0, 0);
        $this->assertCount(2, $all);                     // فقط کانال خواسته‌شده
        $this->assertSame('سلام', $all[1]['payload']['n']);

        $afterFirst = $broker->poll(['orders.1'], 0, $all[0]['id']);
        $this->assertCount(1, $afterFirst);               // مکان‌نما کار می‌کند

        $multi = $broker->poll(['orders.1', 'orders.2'], 0, 0);
        $this->assertCount(3, $multi);

        $this->assertSame(3, $broker->lastId());

        // fan-out: دو مصرف‌کننده با مکان‌نمای مستقل
        $consumerA = $broker->poll(['orders.1'], 0, 0);
        $consumerB = $broker->poll(['orders.1'], 0, 0);
        $this->assertSame(\count($consumerA), \count($consumerB));

        // prune با Clock
        Clock::setTestNow('-2 hours');
        $broker->publish('orders.9', 'old', []);
        Clock::setTestNow(null);
        $pruned = $broker->prune();
        $this->assertTrue($pruned >= 1);
        $this->assertSame([], $broker->poll(['orders.9'], 0, 0));
    }

    public function testRedisBrokerWithFakeClient(): void
    {
        $fake = new FakeRealtimeRedis();
        $broker = new RedisBroker($fake, 100);

        $broker->publish('orders.1', 'order.status', ['s' => 'new']);
        $broker->publish('orders.1', 'order.status', ['s' => 'paid']);
        $broker->publish('orders.2', 'other', []);

        $events = $broker->poll(['orders.1'], 0, 0);
        $this->assertCount(2, $events);
        $this->assertSame('new', $events[0]['payload']['s']);
        $this->assertSame(1, $events[0]['id']);
        $this->assertSame(2, $events[1]['id']);

        $afterFirst = $broker->poll(['orders.1'], 0, 1);
        $this->assertCount(1, $afterFirst);

        $this->assertSame(3, $broker->lastId());

        // سقف لیست (LTRIM)
        $small = new RedisBroker($fake, 2);
        $small->publish('capped', 'a', []);
        $small->publish('capped', 'b', []);
        $small->publish('capped', 'c', []);
        $this->assertCount(2, $fake->lists['tvn:rt:ch:capped']);
    }

    public function testArrayBrokerFanOutAndCap(): void
    {
        $broker = new ArrayBroker(3);
        $broker->publish('a', 'e1', []);
        $broker->publish('a', 'e2', []);
        $broker->publish('a', 'e3', []);
        $broker->publish('a', 'e4', []);   // cap=3 → e1 دور می‌افتد

        $all = $broker->poll(['a'], 0, 0);
        $this->assertCount(3, $all);
        $this->assertSame('e2', $all[0]['name']);
        $this->assertSame(4, $broker->lastId());
    }

    // ─── T-06: Presence ──────────────────────────────────────────────────

    public function testPresenceJoinLeaveMembers(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $presence = new PresenceManager($cache, 90);

        $presence->join('presence:chat.1', ['id' => '1', 'name' => 'علی']);
        $presence->join('presence:chat.1', ['id' => '2', 'name' => 'مریم']);

        $members = $presence->members('presence:chat.1');
        $this->assertCount(2, $members);
        $names = \array_column($members, 'name');
        $this->assertContains('علی', $names);
        $this->assertSame(2, $presence->count('presence:chat.1'));

        // heartbeat → تمدید (بدون تغییر اعضا)
        $presence->join('presence:chat.1', ['id' => '1', 'name' => 'علی']);
        $this->assertSame(2, $presence->count('presence:chat.1'));

        $presence->leave('presence:chat.1', '1');
        $this->assertSame(1, $presence->count('presence:chat.1'));
        $this->assertSame('مریم', $presence->members('presence:chat.1')[0]['name']);
    }

    public function testPresenceExpiresMembers(): void
    {
        $cache = $this->app->make('cache')->store('array');
        $presence = new PresenceManager($cache, 90);

        Clock::setTestNow('2026-06-01 12:00:00');
        $presence->join('presence:chat.1', ['id' => '1', 'name' => 'علی']);

        // ۹۱ ثانیه بعد بدون heartbeat → منقضی
        Clock::setTestNow('2026-06-01 12:01:31');
        $this->assertSame([], $presence->members('presence:chat.1'));
        $this->assertSame(0, $presence->count('presence:chat.1'));
    }

    // ─── Realtime facade ─────────────────────────────────────────────────

    public function testRealtimeFacadePresence(): void
    {
        $facadeRoot = $this->app->make('realtime.facade');
        $facadeRoot->join('presence:chat.7', ['id' => '3', 'name' => 'رضا']);

        $members = \Tavaan\Core\Facade\Facades\Realtime::presence('presence:chat.7');
        $this->assertCount(1, $members);
        $this->assertSame('رضا', $members[0]['name']);

        \Tavaan\Core\Facade\Facades\Realtime::publish('public.news', 'bulletin', ['t' => 'x']);
        $events = \Tavaan\Core\Facade\Facades\Broadcast::broker()->poll(['public.news'], 0, 0);
        $this->assertCount(1, $events);
    }

    public function testRoutesAndBindingsRegistered(): void
    {
        $router = $this->app->make('router');
        $this->assertTrue($router->has('realtime.stream'));
        $this->assertTrue($router->has('realtime.auth'));
        $this->assertTrue($this->app->bound('realtime'));
        $this->assertTrue($this->app->bound('broadcast'));
        $this->assertTrue($this->app->bound('realtime.presence'));
    }
}
