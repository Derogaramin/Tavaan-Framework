<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\MailException;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * SmtpTransport — کلاینت SMTP **خالص خودنوشته** با stream socket (بدون ext؛ DOC-065 §5).
 *
 * - STARTTLS با `stream_socket_enable_crypto`
 * - SSL با prefix `ssl://`
 * - AUTH LOGIN / PLAIN
 * - 🔒 Timeout اجباری برای همهٔ عملیات socket
 * - 🔒 Dot-stuffing برای DATA (RFC 5321 §4.5.2)
 *
 * برای تست: `$socketFactory` و `$cryptoEnabler` قابل تزریق‌اند.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class SmtpTransport implements TransportInterface
{
    private readonly string $host;

    private readonly int $port;

    /** '' | 'tls' (STARTTLS) | 'ssl' (implicit) */
    private readonly string $encryption;

    private readonly string $username;

    private readonly string $password;

    private readonly int $timeout;

    private readonly string $localDomain;

    /** @var \Closure(string, int, float, bool): resource|null */
    private readonly ?\Closure $socketFactory;

    /** @var \Closure(resource, int): bool|null */
    private readonly ?\Closure $cryptoEnabler;

    /**
     * @param array<string, mixed> $config {host, port, encryption, username, password, timeout, local_domain}
     */
    public function __construct(
        array $config = [],
        ?\Closure $socketFactory = null,
        ?\Closure $cryptoEnabler = null,
    ) {
        $this->host = (string) ($config['host'] ?? 'localhost');
        $this->port = (int) ($config['port'] ?? 25);
        $this->encryption = \strtolower((string) ($config['encryption'] ?? ''));
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 10);
        $this->localDomain = (string) ($config['local_domain'] ?? 'tavaan.local');
        $this->socketFactory = $socketFactory;
        $this->cryptoEnabler = $cryptoEnabler;
    }

    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('SmtpTransport expects ' . Message::class);
        }

        $recipients = $message->allRecipients();
        if ($recipients === []) {
            throw MailException::noRecipients();
        }

        $from = $message->getFrom();
        if ($from === null) {
            throw MailException::invalidAddress('(from is empty)');
        }

        $socket = $this->connect();

        try {
            $this->expect($socket, 220, 'greeting');
            $this->command($socket, 'EHLO ' . $this->localDomain, 250);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                $this->enableEncryption($socket);
                $this->command($socket, 'EHLO ' . $this->localDomain, 250);
            }

            if ($this->username !== '') {
                $this->authenticate($socket);
            }

            $this->command($socket, 'MAIL FROM:<' . $from['address'] . '>', 250);

            foreach ($recipients as $recipient) {
                $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            $this->command($socket, 'DATA', 354);
            $this->write($socket, $this->dotStuff($message->toMimeString()) . "\r\n.\r\n");
            $this->expect($socket, 250, 'DATA-end');

            $this->command($socket, 'QUIT', 221);
        } finally {
            $this->disconnect($socket);
        }

        return new SentMessage($message->id(), $recipients, $this->name(), $message);
    }

    public function name(): string
    {
        return 'smtp';
    }

    // ─── socket ───────────────────────────────────────────────────────────

    /** @return resource */
    private function connect()
    {
        if ($this->socketFactory !== null) {
            $socket = ($this->socketFactory)($this->host, $this->port, (float) $this->timeout, $this->encryption === 'ssl');
            if (!\is_resource($socket)) {
                throw MailException::smtpConnectFailed($this->host, $this->port, 'socket factory returned non-resource');
            }

            return $socket;
        }

        $target = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = \stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @\stream_socket_client($target, $errno, $errstr, (float) $this->timeout, \STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw MailException::smtpConnectFailed($this->host, $this->port, $errstr !== '' ? $errstr : ('errno ' . $errno));
        }

        return $socket;
    }

    /** @param resource $socket */
    private function enableEncryption($socket): void
    {
        if ($this->cryptoEnabler !== null) {
            $ok = (bool) ($this->cryptoEnabler)($socket, $this->timeout);
            if (!$ok) {
                throw MailException::smtpTlsFailed();
            }

            return;
        }

        \stream_set_timeout($socket, $this->timeout);
        $ok = @\stream_socket_enable_crypto($socket, true, \STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) {
            throw MailException::smtpTlsFailed();
        }
    }

    /** @param resource $socket */
    private function disconnect($socket): void
    {
        if (\is_resource($socket)) {
            @\fclose($socket);
        }
    }

    // ─── پروتکل ───────────────────────────────────────────────────────────

    /**
     * ارسال دستور و انتظار کد مشخص.
     *
     * @param resource  $socket
     * @param int|list<int> $expected
     */
    private function command($socket, string $command, int|array $expected): string
    {
        // 🔒 credential هرگز در وضعیت خطا نشت نکند (AUTH payload)
        $this->write($socket, $command . "\r\n");

        return $this->expect($socket, $expected, $this->safeCommandName($command));
    }

    /**
     * خواندن پاسخ (چندخطی: `250-…` ادامه، `250 …` پایان).
     *
     * @param resource  $socket
     * @param int|list<int> $expected
     */
    private function expect($socket, int|array $expected, string $stage): string
    {
        $expectedCodes = (array) $expected;
        $response = '';

        while (true) {
            $line = $this->readLine($socket);
            $response .= $line;

            if (\strlen($line) < 4) {
                throw MailException::smtpUnexpectedResponse($stage, $response);
            }

            $code = (int) \substr($line, 0, 3);
            $isLast = $line[3] === ' ';

            if ($isLast) {
                if (!\in_array($code, $expectedCodes, true)) {
                    throw MailException::smtpUnexpectedResponse($stage, $response);
                }

                return $response;
            }
        }
    }

    /** @param resource $socket */
    private function authenticate($socket): void
    {
        $this->command($socket, 'AUTH LOGIN', 334);
        $this->command($socket, \base64_encode($this->username), 334);
        $this->command($socket, \base64_encode($this->password), 235);
    }

    /** @param resource $socket */
    private function write($socket, string $data): void
    {
        \stream_set_timeout($socket, $this->timeout);

        $length = \strlen($data);
        $written = 0;
        while ($written < $length) {
            $bytes = @\fwrite($socket, \substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw MailException::transportFailed('smtp', 'write failed');
            }
            $written += $bytes;
        }
    }

    /** @param resource $socket */
    private function readLine($socket): string
    {
        \stream_set_timeout($socket, $this->timeout);

        $line = \fgets($socket, 8192);
        if ($line === false) {
            $meta = \stream_get_meta_data($socket);
            throw MailException::transportFailed('smtp', ($meta['timed_out'] ?? false) ? 'read timeout' : 'read failed');
        }

        $meta = \stream_get_meta_data($socket);
        if ((bool) ($meta['timed_out'] ?? false)) {
            throw MailException::transportFailed('smtp', 'read timeout');
        }

        return $line;
    }

    /** 🔒 RFC 5321 §4.5.2 — خطوطی که با `.` شروع می‌شوند یکی اضافه می‌گیرند. */
    private function dotStuff(string $mime): string
    {
        $normalized = \str_replace(["\r\n", "\r"], "\n", $mime);

        return \str_replace("\n", "\r\n", \preg_replace('/^\./m', '..', $normalized) ?? $normalized);
    }

    /** نام امن دستور برای context خطا (بدون نشت credential). */
    private function safeCommandName(string $command): string
    {
        $verb = \strtoupper((string) \strtok($command, ' '));

        return \in_array($verb, ['EHLO', 'HELO', 'MAIL', 'RCPT', 'DATA', 'QUIT', 'STARTTLS', 'AUTH', 'RSET'], true)
            ? $verb
            : 'CMD';
    }
}
