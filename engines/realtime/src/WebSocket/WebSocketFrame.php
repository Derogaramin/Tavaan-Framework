<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime\WebSocket;

/**
 * تَوان | Tavaan — Realtime
 * WebSocketFrame — کدک فریم RFC 6455 (خودنوشته، بدون ext).
 *
 * - decode: تدریجی (incremental) — اگر بایت‌ها کامل نباشند null
 * - encode: server→client بدون mask؛ client→server با mask اجباری
 * - opcodeها: TEXT=0x1, BINARY=0x2, CLOSE=0x8, PING=0x9, PONG=0xA, CONT=0x0
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class WebSocketFrame
{
    public const OP_CONT = 0x0;
    public const OP_TEXT = 0x1;
    public const OP_BINARY = 0x2;
    public const OP_CLOSE = 0x8;
    public const OP_PING = 0x9;
    public const OP_PONG = 0xA;

    /** سقف payload پذیرفته‌شده (16MB) — 🔒 DoS guard */
    public const MAX_PAYLOAD = 16_777_216;

    private function __construct(
        public readonly bool $fin,
        public readonly int $opcode,
        public readonly string $payload,
        public readonly int $consumed,
    ) {
    }

    /**
     * رمزگشایی یک فریم از ابتدای buffer.
     *
     * @return self|null null = بایت‌ها کامل نیستند (منتظر بمان)
     *
     * @throws WebSocketProtocolException فریم نامعتبر/بیش‌ازحد بزرگ
     */
    public static function decode(string $buffer, bool $expectMasked = true): ?self
    {
        $len = \strlen($buffer);
        if ($len < 2) {
            return null;
        }

        $b0 = \ord($buffer[0]);
        $b1 = \ord($buffer[1]);

        $fin = ($b0 & 0x80) !== 0;
        $rsv = $b0 & 0x70;
        if ($rsv !== 0) {
            throw WebSocketProtocolException::invalidFrame('RSV bits must be 0');
        }
        $opcode = $b0 & 0x0F;
        if (!\in_array($opcode, [self::OP_CONT, self::OP_TEXT, self::OP_BINARY, self::OP_CLOSE, self::OP_PING, self::OP_PONG], true)) {
            throw WebSocketProtocolException::invalidFrame('unknown opcode ' . $opcode);
        }

        $masked = ($b1 & 0x80) !== 0;
        if ($expectMasked && !$masked) {
            // 🔒 RFC 6455 §5.1: فریم client→server باید masked باشد
            throw WebSocketProtocolException::invalidFrame('client frame must be masked');
        }

        $payloadLen = $b1 & 0x7F;
        $offset = 2;

        if ($payloadLen === 126) {
            if ($len < $offset + 2) {
                return null;
            }
            /** @var array{1: int} $u */
            $u = \unpack('n', \substr($buffer, $offset, 2));
            $payloadLen = (int) $u[1];
            $offset += 2;
        } elseif ($payloadLen === 127) {
            if ($len < $offset + 8) {
                return null;
            }
            /** @var array{1: int} $u */
            $u = \unpack('J', \substr($buffer, $offset, 8));
            $payloadLen = (int) $u[1];
            $offset += 8;
        }

        if ($payloadLen > self::MAX_PAYLOAD) {
            throw WebSocketProtocolException::invalidFrame('payload too large: ' . $payloadLen);
        }

        $maskKey = '';
        if ($masked) {
            if ($len < $offset + 4) {
                return null;
            }
            $maskKey = \substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($len < $offset + $payloadLen) {
            return null;   // هنوز کامل نیست
        }

        $payload = \substr($buffer, $offset, $payloadLen);
        if ($masked) {
            $payload = self::applyMask($payload, $maskKey);
        }
        $offset += $payloadLen;

        return new self($fin, $opcode, $payload, $offset);
    }

    /** رمزگذاری فریم (server→client بدون mask). */
    public static function encode(string $payload, int $opcode = self::OP_TEXT, bool $fin = true, bool $mask = false): string
    {
        $len = \strlen($payload);
        $out = \chr(($fin ? 0x80 : 0x00) | ($opcode & 0x0F));

        $maskBit = $mask ? 0x80 : 0x00;
        if ($len < 126) {
            $out .= \chr($maskBit | $len);
        } elseif ($len <= 0xFFFF) {
            $out .= \chr($maskBit | 126) . \pack('n', $len);
        } else {
            $out .= \chr($maskBit | 127) . \pack('J', $len);
        }

        if ($mask) {
            $key = \random_bytes(4);
            $out .= $key . self::applyMask($payload, $key);
        } else {
            $out .= $payload;
        }

        return $out;
    }

    public static function textFrame(string $payload): string
    {
        return self::encode($payload, self::OP_TEXT);
    }

    /** فریم close با status code. */
    public static function close(int $status = 1000, string $reason = ''): string
    {
        return self::encode(\pack('n', $status) . $reason, self::OP_CLOSE);
    }

    public static function pong(string $payload = ''): string
    {
        return self::encode($payload, self::OP_PONG);
    }

    public function isText(): bool
    {
        return $this->opcode === self::OP_TEXT;
    }

    public function isClose(): bool
    {
        return $this->opcode === self::OP_CLOSE;
    }

    public function isPing(): bool
    {
        return $this->opcode === self::OP_PING;
    }

    /** payload متن (UTF-8 sanitize شده برای JSON). */
    public function text(): string
    {
        if (!\mb_check_encoding($this->payload, 'UTF-8')) {
            return '';
        }

        return $this->payload;
    }

    /** @return array{code: int, reason: string} */
    public function closeStatus(): array
    {
        if (\strlen($this->payload) >= 2) {
            /** @var array{1: int} $u */
            $u = \unpack('n', \substr($this->payload, 0, 2));

            return ['code' => (int) $u[1], 'reason' => \substr($this->payload, 2)];
        }

        return ['code' => 1005, 'reason' => ''];
    }

    /** XOR ماسک RFC 6455 (symmetric — رمزگشا=رمزگذار). */
    public static function applyMask(string $data, string $key): string
    {
        if (\strlen($key) !== 4) {
            return $data;
        }
        $out = $data;
        $len = \strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $out[$i] = $data[$i] ^ $key[$i % 4];
        }

        return $out;
    }

    /**
     * کلید پذیرش handshake (RFC 6455 §1.3).
     * `base64(sha1(key + GUID, raw))`
     */
    public static function acceptKey(string $clientKey): string
    {
        return \base64_encode(\sha1(\trim($clientKey) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }
}
