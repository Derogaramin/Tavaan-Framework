# DOC-045 — موتور Realtime | بلادرنگ (SSE + WebSocket)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-045 |
| Engine ID | `tavaan.engine.realtime` |
| Namespace | `Tavaan\Engine\Realtime` |
| اولویت | **P2** — فاز M6 |
| وابستگی | `event`, `http`, `queue` |
| افزونه | `sockets`(اختیاری برای WebSocket) |
| LOC | ۱۲۰۰ |

## ۱. هدف
ارتباط بلادرنگ با **SSE** (Server-Sent Events، بدون ext) و **WebSocket** (با ext-sockets
یا Swoole)، Channel-based Broadcast، Presence و احراز هویت کانال.

## ۲. دو پروتکل
| پروتکل | نیاز | جهت | کاربرد |
|---|---|---|---|
| **SSE** | هیچ ext | یک‌طرفه (server→client) | Notification، پیشرفت کار، آمار زنده |
| **WebSocket** | `sockets`/Swoole | دوطرفه | چت، همکاری زنده، بازی |

🔒 پیش‌فرض SSE (بدون وابستگی). WebSocket برای موارد دوطرفه.

## ۳. Broadcast (از رویدادها)
```php
// رویداد با ShouldBroadcast
final class OrderStatusChanged implements ShouldBroadcast
{
    public function broadcastOn(): array { return ["orders.{$this->orderId}"]; }
    public function broadcastAs(): string { return 'order.status'; }
    public function broadcastWith(): array { return ['status'=>$this->status]; }
}

// در routes/channels.php
Broadcast::channel('orders.{orderId}', function (User $user, int $orderId): bool {
    return $user->can('shop.order.view', Order::find($orderId));   // 🔒 احراز کانال
});
Broadcast::channel('chat.{room}', fn (User $u, Room $r) => $r->hasMember($u));
```

## ۴. SSE Server
```php
final class SseController
{
    public function stream(Request $request): Response
    {
        return response()->stream(function () use ($request): void {
            \set_time_limit(0);
            \ob_implicit_flush(true);

            $channels = $this->authorizeChannels($request);
            \ob_start();
            echo "retry: 3000\n\n";         // reconnect بعد از ۳s
            \ob_flush(); \flush();

            while (!$this->shouldStop()) {
                $events = $this->broker->poll($channels, timeout: 1);   // از Redis/DB
                foreach ($events as $event) {
                    echo "event: {$event->name}\n";
                    echo "data: " . \json_encode($event->payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                }
                echo ": keep-alive\n\n";    // heartbeat
                \flush();
                if (\connection_aborted()) { break; }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',     // 🔒 غیرفعال‌کردن buffer در nginx
        ]);
    }
}
```
کلاینت:
```javascript
const es = new EventSource('/events/stream?channels=orders.42');
es.addEventListener('order.status', (e) => console.log(JSON.parse(e.data)));
```

## ۵. WebSocket Server (با sockets)
```php
final class WebSocketServer
{
    public function run(string $host = '0.0.0.0', int $port = 8081): never
    {
        $socket = \stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        $clients = [];

        while (true) {
            $read = \array_merge([$socket], $clients);
            \stream_select($read, $w, $e, 0, 200000);

            foreach ($read as $sock) {
                if ($sock === $socket) {
                    $client = \stream_socket_accept($socket);
                    $this->handshake($client);       // HTTP Upgrade
                    $clients[] = $client;
                } else {
                    $msg = $this->readFrame($sock);
                    if ($msg === null) { $this->disconnect($sock, $clients); continue; }
                    $this->handleMessage($sock, $msg, $clients);
                }
            }
            $this->broadcastPending($clients);        // از queue
        }
    }

    private function handshake($client): void
    {
        $request = \fread($client, 2048);
        \preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $request, $m);
        $accept = \base64_encode(\sha1(\trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        \fwrite($client, "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n");
    }
}
```
🔒 WebSocket frame parsing/masking خودنوشته (RFC 6455).
💡 برای مقیاس بالا → Swoole WebSocket Server (در حالت resident).

## ۶. Message Broker (Fan-out بین Workerها)
```php
interface BrokerInterface
{
    public function publish(string $channel, Event $event): void;
    public function poll(array $channels, int $timeout): array;   // برای SSE/WS
    public function subscribe(string $channel, string $subscriberId): void;
}
// درایورها: Redis Pub/Sub (پیش‌فرض تولید)، Database polling (بدون Redis)، Array (تست)
```

## ۷. Presence (کاربران آنلاین)
```php
Broadcast::channel('chat.{room}', fn($u,$r) => ['id'=>$u->id,'name'=>$u->name]);
// → در client: members here
Realtime::presence('chat.1');    // list حاضران
```

## ۸. احراز هویت کانال 🔒
```php
// POST /broadcasting/auth  (برای private channel)
final class BroadcastingAuthController
{
    public function auth(Request $request): Response
    {
        $channel = $request->input('channel_name');
        $user = $request->user();
        if ($user === null) { return response()->json(['error'=>'unauthenticated'], 403); }

        $callback = $this->channels->resolve($channel);
        if ($callback === null || !$callback($user, ...$this->params($channel))) {
            return response()->json(['error'=>'forbidden'], 403);
        }
        return response()->json(['auth' => $this->sign($channel, $user)]);
    }
}
```

## ۹. پیکربندی
```php
// config/realtime.php
return [
    'driver' => env_string('TVN_REALTIME_DRIVER', 'sse'),   // sse|websocket|swoole
    'broker' => env_string('TVN_REALTIME_BROKER', 'database'),   // redis|database|array
    'sse' => ['endpoint'=>'/events/stream', 'heartbeat'=>15, 'max_connections'=>1000],
    'websocket' => ['host'=>'0.0.0.0', 'port'=>8081, 'max_clients'=>1000],
    'auth_endpoint' => '/broadcasting/auth',
];
```

## ۱۰. امنیت
| تهدید | کنترل |
|---|---|
| شنود کانال خصوصی | احراز کانال (channels.php) |
| DoS با اتصال زیاد | max_connections + heartbeat timeout |
| Message Injection | escape در broadcastWith |
| Origin در WebSocket | بررسی Origin header در handshake |

## ۱۱. تست
| T-01 | SSE stream + event format |
| T-02 | Broadcast از رویداد ShouldBroadcast |
| T-03 | احراز کانال private |
| T-04 | WebSocket handshake |
| T-05 | Frame parse/mask |
| T-06 | Presence |
| T-07 | Redis/DB broker fan-out |
| T-08 | Heartbeat + reconnect |

## ۱۲. معیار پذیرش 🧪
- AC-RT-01..08. AC-RT-09: SSE بدون ext کار می‌کند.

## ۱۳. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Echo/Broadcasting | channel/broadcast | SSE داخلی (بدون Pusher)، WebSocket خودنوشته |
| Socket.IO | WebSocket | بدون پروتکل اختصاصی |
| W3C SSE | standard | — |

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.realtime.v1
engine:
  id: tavaan.engine.realtime
  namespace: Tavaan\Engine\Realtime
  path: engines/realtime
  priority: P2
  phase: M6
  depends_on: [event, http, queue]
  extensions: {required: [], optional: [{name: sockets, fallback: sse}, {name: swoole, fallback: sockets}]}
  protocols: [sse, websocket]
  config_file: config/realtime.php
  public_api:
    - class: RealtimeManager
      methods: [broadcast, presence, publish]
    - class: Broadcast
      methods: [channel, connection]
  loc_estimate: 1200
```
