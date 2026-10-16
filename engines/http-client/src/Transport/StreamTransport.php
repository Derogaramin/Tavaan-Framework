<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Transport;

use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;

/**
 * تَوان | Tavaan — HttpClient
 * StreamTransport — ارسال با stream wrapper داخلی PHP (بدون curl؛ DOC-064 §3.1).
 *
 * 🔒 `follow_location = 0` تا redirectها توسط PendingRequest با SSRF
 * re-check مدیریت شوند (DOC-064 §3.1).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class StreamTransport implements HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): TransportResponse
    {
        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name . ': ' . $value . "\r\n";
        }

        $httpOptions = [
            'method' => \strtoupper($method),
            'header' => $headerLines,
            'timeout' => (float) ($options['timeout'] ?? 10),
            'follow_location' => 0,        // 🔒 redirect دستی
            'ignore_errors' => true,       // بدنهٔ 4xx/5xx هم خوانده شود
            'protocol_version' => 1.1,
        ];
        if ($body !== null) {
            $httpOptions['content'] = $body;
        }
        if (!empty($options['proxy'])) {
            $httpOptions['proxy'] = (string) $options['proxy'];
        }

        $sslOptions = [
            'verify_peer' => (bool) ($options['verify_tls'] ?? true),
            'verify_peer_name' => (bool) ($options['verify_tls'] ?? true),
        ];
        if (!empty($options['ca_bundle'])) {
            $sslOptions['cafile'] = (string) $options['ca_bundle'];
        }

        $context = \stream_context_create(['http' => $httpOptions, 'ssl' => $sslOptions]);

        // 🔒 محدودیت حجم پاسخ (DoS با پاسخ بزرگ)
        $maxSize = (int) ($options['max_size'] ?? 0);
        if ($maxSize > 0) {
            $httpOptions['max_redirects'] = 0;
        }

        $stream = @\fopen($url, 'rb', false, $context);
        if ($stream === false) {
            $error = \error_get_last()['message'] ?? 'unknown';

            throw HttpClientException::connectionFailed($url, $error);
        }

        try {
            $meta = \stream_get_meta_data($stream);
            $status = 0;
            $responseHeaders = [];
            foreach ((array) ($meta['wrapper_data'] ?? []) as $line) {
                $line = (string) $line;
                if (\preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                    $status = (int) $m[1];
                    $responseHeaders = [];     // فقط هدرهای آخرین پاسخ
                    continue;
                }
                if (\str_contains($line, ':')) {
                    [$name, $value] = \explode(':', $line, 2);
                    $responseHeaders[\strtolower(\trim($name))] = \trim($value);
                }
            }

            // خواندن chunk-chunk با سقف max_size (حافظهٔ کنترل‌شده)
            $responseBody = '';
            while (!\feof($stream)) {
                $chunk = \fread($stream, 8192);
                if ($chunk === false) {
                    break;
                }
                $responseBody .= $chunk;
                if ($maxSize > 0 && \strlen($responseBody) > $maxSize) {
                    throw HttpClientException::responseTooLarge($url, $maxSize);
                }
            }
        } finally {
            \fclose($stream);
        }

        return new TransportResponse($status, $responseHeaders, $responseBody, $url);
    }

    public function name(): string
    {
        return 'stream';
    }
}
