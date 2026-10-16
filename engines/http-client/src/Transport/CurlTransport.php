<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Transport;

use Tavaan\Engine\HttpClient\Exceptions\HttpClientException;

/**
 * تَوان | Tavaan — HttpClient
 * CurlTransport — ارسال با ext-curl (DOC-064 §3.1).
 *
 * 🔒 `CURLOPT_FOLLOWLOCATION = false` — redirectها توسط PendingRequest
 * با SSRF re-check مدیریت می‌شوند (DOC-064 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class CurlTransport implements HttpTransportInterface
{
    public static function available(): bool
    {
        return \function_exists('curl_init') && \function_exists('curl_multi_init');
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): TransportResponse
    {
        $handle = $this->createHandle($method, $url, $headers, $body, $options);

        $raw = \curl_exec($handle);
        if ($raw === false) {
            $error = \curl_error($handle);
            $errno = \curl_errno($handle);
            \curl_close($handle);

            throw HttpClientException::connectionFailed($url, \sprintf('curl error %d: %s', $errno, $error));
        }

        $status = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        $headerSize = (int) \curl_getinfo($handle, \CURLINFO_HEADER_SIZE);
        $effectiveUrl = (string) \curl_getinfo($handle, \CURLINFO_EFFECTIVE_URL);
        \curl_close($handle);

        $rawString = (string) $raw;
        $rawHeaders = \substr($rawString, 0, $headerSize);
        $responseBody = \substr($rawString, $headerSize);

        $maxSize = (int) ($options['max_size'] ?? 0);
        if ($maxSize > 0 && \strlen($responseBody) > $maxSize) {
            throw HttpClientException::responseTooLarge($url, $maxSize);
        }

        return new TransportResponse($status, self::parseHeaders($rawHeaders), $responseBody, $effectiveUrl !== '' ? $effectiveUrl : $url);
    }

    public function name(): string
    {
        return 'curl';
    }

    /**
     * ارسال موازی با curl_multi (DOC-064 §8: Pool موازی).
     *
     * هر job: `{key, method, url, headers, body, options, ssrf: Closure(string): bool}`.
     * 🔒 redirectها حین multi با SSRF re-check دنبال می‌شوند.
     *
     * @param list<array<string, mixed>> $jobs
     *
     * @return array<string, TransportResponse|\Throwable>
     */
    public function sendConcurrent(array $jobs, int $maxRedirects = 5): array
    {
        $multi = \curl_multi_init();
        if ($multi === false) {
            throw HttpClientException::connectionFailed('multi', 'curl_multi_init failed');
        }

        /** @var array<int, array{job: array<string, mixed>, url: string, redirects: int}> $active */
        $active = [];
        /** @var array<string, TransportResponse|\Throwable> $results */
        $results = [];

        $addJob = static function (array $job, string $url, int $redirects) use ($multi, &$active): void {
            $handle = \curl_init();
            if ($handle === false) {
                return;
            }
            $headerLines = [];
            foreach ((array) $job['headers'] as $name => $value) {
                $headerLines[] = $name . ': ' . $value;
            }
            \curl_setopt_array($handle, [
                \CURLOPT_URL => $url,
                \CURLOPT_CUSTOMREQUEST => \strtoupper((string) $job['method']),
                \CURLOPT_HTTPHEADER => $headerLines,
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_HEADER => true,
                \CURLOPT_FOLLOWLOCATION => false,
                \CURLOPT_TIMEOUT => (int) ($job['options']['timeout'] ?? 10),
                \CURLOPT_CONNECTTIMEOUT => (int) ($job['options']['connect_timeout'] ?? 5),
                \CURLOPT_SSL_VERIFYPEER => (bool) ($job['options']['verify_tls'] ?? true),
                \CURLOPT_SSL_VERIFYHOST => ($job['options']['verify_tls'] ?? true) ? 2 : 0,
                \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            ]);
            if (($job['body'] ?? null) !== null) {
                \curl_setopt($handle, \CURLOPT_POSTFIELDS, $job['body']);
            }
            $active[(int) $handle] = ['job' => $job, 'url' => $url, 'redirects' => $redirects, 'handle' => $handle];
            \curl_multi_add_handle($multi, $handle);
        };

        foreach ($jobs as $job) {
            $addJob($job, (string) $job['url'], 0);
        }

        do {
            do {
                $status = \curl_multi_exec($multi, $running);
            } while ($status === \CURLM_CALL_MULTI_PERFORM);

            if ($running > 0) {
                \curl_multi_select($multi, 0.1);
            }

            while (($info = \curl_multi_info_read($multi)) !== false) {
                /** @var \CurlHandle $handle */
                $handle = $info['handle'];
                $entry = $active[(int) $handle] ?? null;
                if ($entry === null) {
                    continue;
                }

                $job = $entry['job'];
                $key = (string) $job['key'];
                $currentUrl = (string) $entry['url'];
                \curl_multi_remove_handle($multi, $handle);

                if ($info['result'] !== \CURLE_OK) {
                    $results[$key] = HttpClientException::connectionFailed($currentUrl, \curl_strerror($info['result']) ?: ('curl errno ' . $info['result']));
                    \curl_close($handle);
                    unset($active[(int) $handle]);
                    continue;
                }

                $raw = (string) \curl_multi_getcontent($handle);
                $code = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
                $headerSize = (int) \curl_getinfo($handle, \CURLINFO_HEADER_SIZE);
                \curl_close($handle);
                unset($active[(int) $handle]);

                $response = new TransportResponse(
                    $code,
                    self::parseHeaders(\substr($raw, 0, $headerSize)),
                    \substr($raw, $headerSize),
                    $currentUrl,
                );

                // redirect با 🔒 SSRF re-check
                if ($response->isRedirect() && $entry['redirects'] < $maxRedirects) {
                    $location = $response->header('location');
                    if ($location !== null && $location !== '') {
                        $next = $this->resolveUrl($currentUrl, $location);
                        $ssrf = $job['ssrf'] ?? null;
                        if ($ssrf instanceof \Closure && !$ssrf($next)) {
                            $results[$key] = HttpClientException::connectionFailed($next, 'redirect blocked by SSRF guard');
                            continue;
                        }
                        $method = \strtoupper((string) $job['method']);
                        if ($response->status === 303 || (!\in_array($response->status, [307, 308], true) && $method !== 'GET' && $method !== 'HEAD')) {
                            $job['method'] = 'GET';
                            $job['body'] = null;
                            unset($job['headers']['Content-Type'], $job['headers']['Content-Length']);
                        }
                        $addJob($job, $next, $entry['redirects'] + 1);
                        continue;
                    }
                }

                $results[$key] = $response;
            }
        } while ($running > 0 || $active !== []);

        \curl_multi_close($multi);

        return $results;
    }

    /** ترکیب Location با URL جاری. */
    private function resolveUrl(string $base, string $location): string
    {
        if (\preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = \parse_url($base);
        $origin = (string) ($parts['scheme'] ?? 'http') . '://' . (string) ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (\str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $basePath = (string) (\preg_replace('#/[^/]*$#', '/', (string) ($parts['path'] ?? '/')) ?? '/');

        return $origin . $basePath . $location;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     *
     * @return \CurlHandle
     */
    private function createHandle(string $method, string $url, array $headers, ?string $body, array $options)
    {
        $handle = \curl_init();
        if ($handle === false) {
            throw HttpClientException::connectionFailed($url, 'curl_init failed');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        \curl_setopt_array($handle, [
            \CURLOPT_URL => $url,
            \CURLOPT_CUSTOMREQUEST => \strtoupper($method),
            \CURLOPT_HTTPHEADER => $headerLines,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLOPT_FOLLOWLOCATION => false,   // 🔒 redirect دستی با SSRF re-check
            \CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 10),
            \CURLOPT_CONNECTTIMEOUT => (int) ($options['connect_timeout'] ?? 5),
            \CURLOPT_SSL_VERIFYPEER => (bool) ($options['verify_tls'] ?? true),
            \CURLOPT_SSL_VERIFYHOST => ($options['verify_tls'] ?? true) ? 2 : 0,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
        ]);

        if ($body !== null) {
            \curl_setopt($handle, \CURLOPT_POSTFIELDS, $body);
        }
        if (!empty($options['proxy'])) {
            \curl_setopt($handle, \CURLOPT_PROXY, (string) $options['proxy']);
        }
        if (!empty($options['ca_bundle'])) {
            \curl_setopt($handle, \CURLOPT_CAINFO, (string) $options['ca_bundle']);
        }

        return $handle;
    }

    /** @return array<string, string> */
    public static function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (\explode("\r\n", $raw) as $line) {
            if (\str_contains($line, ':')) {
                [$name, $value] = \explode(':', $line, 2);
                $headers[\strtolower(\trim($name))] = \trim($value);
            }
        }

        return $headers;
    }
}
