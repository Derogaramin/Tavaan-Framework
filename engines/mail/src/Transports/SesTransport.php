<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail\Transports;

use Tavaan\Core\Contracts\Mail\TransportInterface;
use Tavaan\Engine\Mail\MailException;
use Tavaan\Engine\Mail\Message;
use Tavaan\Engine\Mail\SentMessage;

/**
 * تَوان | Tavaan — Mail
 * SesTransport — AWS SES HTTP API v2 با SigV4 خودنوشته (اختیاری؛ DOC-065 §2).
 *
 * 🔒 بدون AWS SDK: امضا با `S3Signer` موتور Filesystem (service=ses)
 * و ارسال با curl/stream. بدنهٔ MIME به‌شکل RawContent (base64) می‌رود.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class SesTransport implements TransportInterface
{
    private readonly string $region;

    private readonly string $key;

    private readonly string $secret;

    private readonly float $timeout;

    /** @var \Closure(string, string, array<string, string>): array{status: int, body: string}|null */
    private readonly ?\Closure $httpSender;

    /**
     * @param array<string, mixed> $config {key, secret, region, timeout}
     */
    public function __construct(array $config = [], ?\Closure $httpSender = null)
    {
        $this->region = (string) ($config['region'] ?? 'us-east-1');
        $this->key = (string) ($config['key'] ?? '');
        $this->secret = (string) ($config['secret'] ?? '');
        $this->timeout = (float) ($config['timeout'] ?? 15.0);
        $this->httpSender = $httpSender;
    }

    public function send(object $message): object
    {
        if (!$message instanceof Message) {
            throw new \InvalidArgumentException('SesTransport expects ' . Message::class);
        }

        $recipients = $message->allRecipients();
        if ($recipients === []) {
            throw MailException::noRecipients();
        }

        $signerClass = \Tavaan\Engine\Filesystem\Adapters\S3Signer::class;
        if (!\class_exists($signerClass)) {
            throw MailException::transportFailed('ses', 'S3Signer (filesystem engine) لازم است');
        }

        $host = 'email.' . $this->region . '.amazonaws.com';
        $path = '/v2/email/outbound-emails';
        $payload = (string) \json_encode([
            'Content' => ['Raw' => ['Data' => \base64_encode($message->toMimeString())]],
        ], \JSON_THROW_ON_ERROR);

        /** @var \Tavaan\Engine\Filesystem\Adapters\S3Signer $signer */
        $signer = new $signerClass($this->key, $this->secret, $this->region, 'ses');
        $headers = $signer->signRequest('POST', $path, [], [
            'host' => $host,
            'content-type' => 'application/json',
        ], $payload);

        $sendHeaders = [];
        foreach ($headers as $name => $value) {
            if (\strtolower($name) !== 'host') {
                $sendHeaders[$name] = $value;
            }
        }

        $url = 'https://' . $host . $path;
        $response = $this->httpSender !== null
            ? ($this->httpSender)('POST', $url, $sendHeaders, $payload)
            : $this->defaultSend($url, $sendHeaders, $payload);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw MailException::sesFailed($response['status'], $response['body']);
        }

        $decoded = \json_decode($response['body'], true);
        $messageId = \is_array($decoded) ? (string) ($decoded['MessageId'] ?? $message->id()) : $message->id();

        return new SentMessage($messageId, $recipients, $this->name(), $message);
    }

    public function name(): string
    {
        return 'ses';
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}
     */
    private function defaultSend(string $url, array $headers, string $payload): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        if (\function_exists('curl_init')) {
            $handle = \curl_init($url);
            if ($handle === false) {
                throw MailException::transportFailed('ses', 'curl_init failed');
            }
            \curl_setopt_array($handle, [
                \CURLOPT_POST => true,
                \CURLOPT_POSTFIELDS => $payload,
                \CURLOPT_HTTPHEADER => $headerLines,
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_TIMEOUT => (int) \ceil($this->timeout),
            ]);
            $body = \curl_exec($handle);
            $status = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
            $error = $body === false ? \curl_error($handle) : '';
            \curl_close($handle);

            if ($body === false) {
                throw MailException::transportFailed('ses', $error);
            }

            return ['status' => $status, 'body' => (string) $body];
        }

        $context = \stream_context_create(['http' => [
            'method' => 'POST',
            'header' => \implode("\r\n", $headerLines),
            'content' => $payload,
            'timeout' => $this->timeout,
            'ignore_errors' => true,
        ]]);

        $body = @\file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (\preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        if ($body === false) {
            throw MailException::transportFailed('ses', 'stream request failed');
        }

        return ['status' => $status, 'body' => $body];
    }
}
