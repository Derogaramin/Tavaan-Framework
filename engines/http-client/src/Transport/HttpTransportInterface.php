<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Transport;

/**
 * تَوان | Tavaan — HttpClient
 * HttpTransportInterface — لایهٔ ارسال خام HTTP (DOC-064 §3.1).
 *
 * پیاده‌سازی‌ها: CurlTransport (ext-curl) و StreamTransport (fallback).
 * 🔒 هیچ پیاده‌سازی‌ای redirect را خودش دنبال نمی‌کند — مدیریت redirect
 * با SSRF re-check در PendingRequest انجام می‌شود (DOC-064 §3.2).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
interface HttpTransportInterface
{
    /**
     * ارسال درخواست خام.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options {timeout, connect_timeout, verify_tls, proxy, max_size}
     *
     * @throws \Tavaan\Engine\HttpClient\Exceptions\ConnectionException در خطای اتصال/timeout
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): TransportResponse;

    /** نام درایور: curl|stream|fake */
    public function name(): string;
}
