<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\WebSocket;

use Tavaan\Core\Contracts\Realtime\BrokerInterface;
use Tavaan\Engine\Realtime\Exceptions\RealtimeException;
use Tavaan\Engine\Realtime\RealtimeManager;

/**
 * تَوان | Tavaan — Realtime
 * WebSocketServer — سرور RFC 6455 خودنوشته با streamها (بدون ext اجباری).
 *
 * - handshake: Sec-WebSocket-Accept = base64(sha1(key + GUID))
 * - 🔒 بررسی Origin (config realtime.websocket.allowed_origins)
 * - فریم‌ها: WebSocketFrame (mask اجباری از client)
 * - پروتکل subscribe: `{"event":"subscribe","channels":[…],"token":"…"}`
 * - fan-out: هر tick رویدادهای broker برای کانال‌های هر client
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class WebSocketServer
{
    /** @var array<int, array{stream: resource, buffer: string, channels: list<string>, cursor: int, fragments: string, fragOpcode: int}> */
    private array $clients = [];

    /** @var resource|null */
    private $server = null;

    private int $nextClientId = 1;

    private int $maxClients;

    /** @var list<string> */
    private array $allowedOrigins;

    private ?BrokerInterface $brokerOverride = null;

    /**
     * @param array<string, mixed> $config کلید realtime.websocket
     */
    public function __construct(
        private readonly RealtimeManager $manager,
        array $config = [],
    ) {
        $this->maxClients = \max(1, (int) ($config['max_clients'] ?? 1000));
        $this->allowedOrigins = \array_map('strval', (array) ($config['allowed_origins'] ?? []));
    }

    /** استفادهٔ تست: broker مشخص به‌جای ساخت از config. */
    public function withBroker(BrokerInterface $broker): self
    {
        $this->brokerOverride = $broker;

        return $this;
    }

    // ─── اجرا ─────────────────────────────────────────────────────────────

    public function run(string $host = '0.0.0.0', int $port = 8081, ?int $maxIterations = null): void
    {
        $errno = 0;
        $errstr = '';
        $server = @\stream_socket_server('tcp://' . $host . ':' . $port, $errno, $errstr);
        if ($server === false) {
            throw RealtimeException::for('realtime.server_start_failed', ['reason' => $errstr ?: (string) $errno]);
        }
        $this->server = $server;
        \stream_set_blocking($server, false);

        $this->loop($maxIterations);
    }

    /** حلقهٔ اصلی — maxIterations برای تست/اجرای محدود. */
    public function loop(?int $maxIterations = null): void
    {
        $iterations = 0;
        while (true) {
            $this->tick();
            $iterations++;
            if ($maxIterations !== null && $iterations >= $maxIterations) {
                break;
            }
        }
    }

    public function tick(): void
    {
        $read = [];
        if ($this->server !== null) {
            $read[] = $this->server;
        }
        foreach ($this->clients as $client) {
            $read[] = $client['stream'];
        }
        if ($read === []) {
            return;
        }

        $write = null;
        $except = null;
        $changed = @\stream_select($read, $write, $except, 0, 200_000);
        if ($changed === false) {
            return;
        }

        foreach ($read as $stream) {
            if ($this->server !== null && $stream === $this->server) {
                $this->accept();
                continue;
            }
            $id = $this->clientIdFor($stream);
            if ($id !== null) {
                $this->handleClientData($id);
            }
        }

        $this->broadcastPending();
    }

    public function clientCount(): int
    {
        return \count($this->clients);
    }

    // ─── handshake ───────────────────────────────────────────────────────

    /**
     * ساخت پاسخ handshake از سرِ درخواست HTTP (خالص — قابل تست).
     *
     * @return string|null پاسخ 101 یا 403/400؛ null یعنی سرِ درخواست ناقص
     */
    public function handshakeResponse(string $requestHead): ?string
    {
        if (!\str_ends_with($requestHead, "\r\n\r\n")) {
            return null;
        }

        $headers = [];
        $lines = \explode("\r\n", $requestHead);
        $requestLine = \array_shift($lines) ?? '';
        if (\preg_match('#^GET\s+\S+\s+HTTP/1\.1#i', $requestLine) !== 1) {
            return "HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        }
        foreach ($lines as $line) {
            if (\str_contains($line, ':')) {
                [$name, $value] = \explode(':', $line, 2);
                $headers[\mb_strtolower(\trim($name))] = \trim($value);
            }
        }

        $upgrade = \mb_strtolower($headers['upgrade'] ?? '');
        if (!\str_contains($upgrade, 'websocket')) {
            return "HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        }

        // 🔒 بررسی Origin (در صورت پیکربندی)
        $origin = $headers['origin'] ?? null;
        if (!$this->originAllowed($origin)) {
            return "HTTP/1.1 403 Forbidden\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        }

        $key = $headers['sec-websocket-key'] ?? '';
        if ($key === '' || \base64_decode($key, true) === false || \strlen((string) \base64_decode($key, true)) !== 16) {
            return "HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\nConnection: close\r\n\r\n";
        }

        $accept = WebSocketFrame::acceptKey($key);

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . 'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n";
    }

    private function originAllowed(?string $origin): bool
    {
        if ($this->allowedOrigins === []) {
            return true;   // پیکربندی نشده → بدون محدودیت (dev)
        }
        if ($origin === null) {
            return false;  // 🔒 لیست فعال است اما Origin غایب → رد
        }

        return \in_array(\rtrim($origin, '/'), $this->allowedOrigins, true);
    }

    // ─── clientها ────────────────────────────────────────────────────────

    private function accept(): void
    {
        if ($this->server === null) {
            return;
        }
        if (\count($this->clients) >= $this->maxClients) {
            $client = @\stream_socket_accept($this->server, 0);
            if (\is_resource($client)) {
                @\fwrite($client, "HTTP/1.1 503 Service Unavailable\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                @\fclose($client);
            }

            return;
        }

        $client = @\stream_socket_accept($this->server, 0);
        if (!\is_resource($client)) {
            return;
        }
        \stream_set_timeout($client, 2);

        // خواندن سرِ handshake (تا \r\n\r\n)
        $head = '';
        $deadline = \microtime(true) + 2.0;
        while (!\str_ends_with($head, "\r\n\r\n") && \microtime(true) < $deadline) {
            $chunk = @\fread($client, 2048);
            if ($chunk === false || ($chunk === '' && \feof($client))) {
                @\fclose($client);

                return;
            }
            if ($chunk !== '') {
                $head .= $chunk;
            }
            if (\strlen($head) > 16384) {
                @\fclose($client);

                return;
            }
        }

        $response = $this->handshakeResponse($head);
        if ($response === null || !\str_starts_with($response, 'HTTP/1.1 101')) {
            @\fwrite($client, (string) $response);
            @\fclose($client);

            return;
        }

        @\fwrite($client, $response);
        \stream_set_blocking($client, false);
        \stream_set_timeout($client, 0);
        $this->addClient($client, $head);
    }

    /**
     * افزودن client با stream آماده (handshake انجام‌شده) — تست/سرور سفارشی.
     *
     * @param resource $stream
     */
    public function attachClient($stream, string $requestHead = ''): int
    {
        return $this->addClient($stream, $requestHead);
    }

    /** @param resource $stream */
    private function addClient($stream, string $requestHead): int
    {
        $id = $this->nextClientId++;
        $cursor = $this->broker()->lastId();

        // کانال‌های درخواستی از query string (عمومی‌ها بلافاصله)
        $channels = [];
        if (\preg_match('#^GET\s+\S*[?&]channels=([^&\s]+)#', $requestHead, $m) === 1) {
            foreach (\explode(',', (string) \urldecode($m[1])) as $channel) {
                $channel = \trim($channel);
                if ($channel !== '' && !$this->manager->isPrivate($channel) && !$this->manager->isPresence($channel)) {
                    $channels[] = $channel;
                }
            }
        }

        $this->clients[$id] = [
            'stream' => $stream,
            'buffer' => '',
            'channels' => $channels,
            'cursor' => $cursor,
            'fragments' => '',
            'fragOpcode' => WebSocketFrame::OP_CONT,
        ];

        return $id;
    }

    private function handleClientData(int $id): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }

        $chunk = @\fread($this->clients[$id]['stream'], 65535);
        if ($chunk === false || ($chunk === '' && @\feof($this->clients[$id]['stream']))) {
            $this->disconnect($id);

            return;
        }
        if ($chunk === '') {
            return;
        }

        $this->clients[$id]['buffer'] .= $chunk;
        if (\strlen($this->clients[$id]['buffer']) > 2 * WebSocketFrame::MAX_PAYLOAD) {
            $this->disconnect($id);   // 🔒 buffer بی‌حد → قطع

            return;
        }

        // حلقهٔ رمزگشایی فریم‌های کامل
        while (isset($this->clients[$id])) {
            try {
                $frame = WebSocketFrame::decode($this->clients[$id]['buffer']);
            } catch (WebSocketProtocolException) {
                $this->disconnect($id, 1002);

                return;
            }
            if ($frame === null) {
                break;   // بایت‌ها کامل نیستند
            }
            $this->clients[$id]['buffer'] = \substr($this->clients[$id]['buffer'], $frame->consumed);

            if ($frame->isClose()) {
                $this->send($id, WebSocketFrame::close($frame->closeStatus()['code'] ?: 1000));
                $this->disconnect($id);

                return;
            }
            if ($frame->isPing()) {
                $this->send($id, WebSocketFrame::pong($frame->payload));
                continue;
            }

            // fragmentation
            if ($frame->opcode === WebSocketFrame::OP_CONT) {
                $this->clients[$id]['fragments'] .= $frame->payload;
                if ($frame->fin) {
                    $message = $this->clients[$id]['fragments'];
                    $this->clients[$id]['fragments'] = '';
                    $this->handleTextMessage($id, $message);
                }
                continue;
            }
            if (!$frame->fin) {
                $this->clients[$id]['fragments'] = $frame->payload;
                $this->clients[$id]['fragOpcode'] = $frame->opcode;
                continue;
            }
            if ($frame->isText()) {
                $this->handleTextMessage($id, $frame->text());
            }
        }
    }

    /** پیام JSON کلاینت — subscribe/unsubscribe/ping. */
    public function handleTextMessage(int $id, string $text): void
    {
        if (!isset($this->clients[$id]) || $text === '') {
            return;
        }
        $message = \json_decode($text, true);
        if (!\is_array($message)) {
            $this->sendJson($id, ['event' => 'error', 'message' => 'invalid json']);

            return;
        }

        $event = (string) ($message['event'] ?? $message['type'] ?? '');

        if ($event === 'subscribe') {
            $token = isset($message['token']) && \is_string($message['token']) ? $message['token'] : null;
            $verified = $token !== null ? $this->manager->verify($token) : null;
            $granted = [];
            foreach ((array) ($message['channels'] ?? []) as $channel) {
                $channel = (string) $channel;
                if ($channel === '' || \count($this->clients[$id]['channels']) >= 64) {
                    continue;
                }
                $private = $this->manager->isPrivate($channel) || $this->manager->isPresence($channel);
                // 🔒 کانال خصوصی فقط با توکن امضاشدهٔ همان کانال
                $allowed = !$private || ($verified !== null && $verified['channel'] === $channel);
                if ($allowed && !\in_array($channel, $this->clients[$id]['channels'], true)) {
                    $this->clients[$id]['channels'][] = $channel;
                    $granted[] = $channel;
                }
            }
            $this->sendJson($id, ['event' => 'subscribed', 'channels' => $granted]);

            return;
        }

        if ($event === 'unsubscribe') {
            $remove = \array_map('strval', (array) ($message['channels'] ?? []));
            $this->clients[$id]['channels'] = \array_values(\array_diff($this->clients[$id]['channels'], $remove));
            $this->sendJson($id, ['event' => 'unsubscribed', 'channels' => $remove]);

            return;
        }

        if ($event === 'ping') {
            $this->sendJson($id, ['event' => 'pong']);

            return;
        }

        $this->sendJson($id, ['event' => 'error', 'message' => 'unknown event']);
    }

    /** fan-out رویدادهای broker به clientها. */
    public function broadcastPending(): void
    {
        if ($this->clients === []) {
            return;
        }
        $broker = $this->broker();

        foreach (\array_keys($this->clients) as $id) {
            $channels = $this->clients[$id]['channels'];
            if ($channels === []) {
                continue;
            }
            $events = $broker->poll($channels, 0, $this->clients[$id]['cursor']);
            foreach ($events as $event) {
                $this->sendJson($id, [
                    'event' => (string) ($event['name'] ?? 'message'),
                    'channel' => (string) ($event['channel'] ?? ''),
                    'data' => (array) ($event['payload'] ?? []),
                    'id' => (int) ($event['id'] ?? 0),
                ]);
                $this->clients[$id]['cursor'] = \max($this->clients[$id]['cursor'], (int) ($event['id'] ?? 0));
            }
        }
    }

    private function send(int $id, string $bytes): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }
        $written = @\fwrite($this->clients[$id]['stream'], $bytes);
        if ($written === false) {
            $this->disconnect($id);
        }
    }

    /** @param array<string, mixed> $data */
    private function sendJson(int $id, array $data): void
    {
        $json = (string) \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->send($id, WebSocketFrame::textFrame($json));
    }

    public function disconnect(int $id, int $closeCode = 1000): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }
        $stream = $this->clients[$id]['stream'];
        unset($this->clients[$id]);
        if (\is_resource($stream)) {
            @\fclose($stream);
        }
        unset($closeCode);
    }

    /** @param resource $stream */
    private function clientIdFor($stream): ?int
    {
        foreach ($this->clients as $id => $client) {
            if ($client['stream'] === $stream) {
                return (int) $id;
            }
        }

        return null;
    }

    private function broker(): BrokerInterface
    {
        return $this->brokerOverride ?? $this->manager->broker();
    }

    public function shutdown(): void
    {
        foreach (\array_keys($this->clients) as $id) {
            $this->send($id, WebSocketFrame::close(1001, 'server shutdown'));
            $this->disconnect($id, 1001);
        }
        if (\is_resource($this->server)) {
            @\fclose($this->server);
        }
        $this->server = null;
    }
}
