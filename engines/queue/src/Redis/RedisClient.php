<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Redis;

use Tavaan\Engine\Queue\Exceptions\QueueException;

/**
 * تَوان | Tavaan — Queue
 * RedisClient — کلاینت حداقلی RESP روی stream socket (صفر‌وابستگی؛ DOC-061 §2).
 *
 * فقط دستورات موردنیاز صف: LIST (RPUSH/LPOP/LLEN/LREM)،
 * ZSET (ZADD/ZREM/ZCARD/ZRANGEBYSCORE) و EVAL/PING/SELECT/AUTH/DEL.
 *
 * 🔒 اتصال تنبل (lazy) — تا اولین دستور socket باز نمی‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class RedisClient implements RedisClientInterface
{
    /** @var resource|null */
    private $socket = null;

    private bool $closed = false;

    /**
     * @param array<string, mixed> $config {host, port, timeout, password, database, prefix}
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * ارسال دستور و خواندن پاسخ.
     *
     * @param list<mixed> $args
     */
    public function command(string $command, mixed ...$args): mixed
    {
        $socket = $this->connect();

        $request = $this->encodeCommand($command, $args);
        $written = @\fwrite($socket, $request);
        if ($written === false || $written === 0) {
            $this->socket = null;

            throw QueueException::for('queue.redis_write_failed', ['command' => $command]);
        }

        return $this->readReply($socket);
    }

    public function ping(): bool
    {
        try {
            return $this->command('PING') === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @\fclose($this->socket);
            $this->socket = null;
        }
        $this->closed = true;
    }

    public function prefix(): string
    {
        return (string) ($this->config['prefix'] ?? 'tvn:');
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /** @return resource */
    private function connect()
    {
        if ($this->socket !== null) {
            return $this->socket;
        }

        if ($this->closed) {
            throw QueueException::for('queue.redis_closed');
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 6379);
        $timeout = (float) ($this->config['timeout'] ?? 3.0);

        $errno = 0;
        $errstr = '';
        $socket = @\stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if ($socket === false) {
            throw QueueException::for(
                'queue.redis_connect_failed',
                ['host' => $host, 'port' => $port, 'error' => $errstr],
                'سرور Redis در دسترس نیست؛ اتصال را در config/queue.php بررسی کنید.',
            );
        }

        \stream_set_timeout($socket, (int) \max(1, \ceil($timeout)));

        $password = (string) ($this->config['password'] ?? '');
        if ($password !== '') {
            $this->socket = $socket;
            $this->command('AUTH', $password);
        } else {
            $this->socket = $socket;
        }

        $database = (int) ($this->config['database'] ?? 0);
        if ($database > 0) {
            $this->command('SELECT', $database);
        }

        return $this->socket;
    }

    /** @param list<mixed> $args */
    private function encodeCommand(string $command, array $args): string
    {
        $parts = \array_merge([$command], $args);
        $out = '*' . \count($parts) . "\r\n";
        foreach ($parts as $part) {
            $part = (string) $part;
            $out .= '$' . \strlen($part) . "\r\n" . $part . "\r\n";
        }

        return $out;
    }

    /** @param resource $socket */
    private function readReply($socket): mixed
    {
        $line = $this->readLine($socket);
        if ($line === '') {
            throw QueueException::for('queue.redis_read_failed');
        }

        $type = $line[0];
        $body = \substr($line, 1);

        return match ($type) {
            '+' => $body,
            '-' => throw QueueException::for('queue.redis_error', ['error' => $body]),
            ':' => (int) $body,
            '$' => $this->readBulk($socket, (int) $body),
            '*' => $this->readMulti($socket, (int) $body),
            default => throw QueueException::for('queue.redis_read_failed', ['type' => $type]),
        };
    }

    /** @param resource $socket */
    private function readBulk($socket, int $length): ?string
    {
        if ($length === -1) {
            return null;
        }

        $data = '';
        $remaining = $length + 2;    // + CRLF
        while ($remaining > 0) {
            $chunk = \fread($socket, $remaining);
            if ($chunk === false || $chunk === '') {
                throw QueueException::for('queue.redis_read_failed');
            }
            $data .= $chunk;
            $remaining -= \strlen($chunk);
        }

        return \substr($data, 0, $length);
    }

    /** @param resource $socket */
    private function readMulti($socket, int $count): ?array
    {
        if ($count === -1) {
            return null;
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readReply($socket);
        }

        return $items;
    }

    /** @param resource $socket */
    private function readLine($socket): string
    {
        $line = \fgets($socket);
        if ($line === false) {
            throw QueueException::for('queue.redis_read_failed');
        }

        return \rtrim($line, "\r\n");
    }
}
