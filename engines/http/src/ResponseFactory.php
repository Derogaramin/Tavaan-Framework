<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Compat\MagicBytesDetector;

/**
 * تَوان | Tavaan — Http
 * ResponseFactory — سازندهٔ پاسخ‌ها (response() helper — DOC-039 §4.1).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class ResponseFactory
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param array<string, string> $headers */
    public function make(string $body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, $headers);
    }

    /** @param int $flags */
    public function json(mixed $data, int $status = 200, int $flags = 0, array $headers = []): Response
    {
        return Response::json($data, $status, $flags, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function view(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        $factory = $this->container->make('view');
        $html = $factory->make($template, $data)->render();

        return new Response($html, $status, $headers);
    }

    /** @param array<string, string> $headers */
    public function file(string $path, array $headers = [], bool $inline = true, ?string $name = null): Response
    {
        $this->assertSafePath($path);

        $mime = MagicBytesDetector::detectFromFile($path) ?? 'application/octet-stream';
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $this->safeFilename($name ?? \basename($path)) . '"';

        $response = new Response((string) \file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) \filesize($path),
            'X-Content-Type-Options' => 'nosniff',
            ...$headers,
        ]);

        return $response;
    }

    /** دانلود فایل (attachment). */
    public function download(string $path, ?string $name = null, array $headers = []): Response
    {
        return $this->file($path, $headers, inline: false, name: $name);
    }

    /** @param \Closure(): void $callback @param array<string, string> $headers */
    public function stream(\Closure $callback, int $status = 200, array $headers = []): StreamedResponse
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /** @param array<string, string> $headers */
    public function streamDownload(\Closure $callback, string $name, array $headers = []): StreamedResponse
    {
        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $this->safeFilename($name) . '"',
            ...$headers,
        ]);
    }

    public function noContent(int $status = 204): Response
    {
        return new Response('', $status);
    }

    /** RFC 9457 problem details. @param array<string, mixed> $extra */
    public function problem(string $title, int $status = 400, array $extra = []): Response
    {
        $body = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            ...$extra,
        ];

        return Response::json($body, $status)->withHeader('Content-Type', 'application/problem+json; charset=UTF-8');
    }

    /**
     * 🔒 Path Traversal guard برای file/download.
     *
     * 🔧 cross-platform (PathGuard): containment پیش از وجودسنجی بررسی می‌شود
     * تا مسیر بیرونیِ وجود‌نداشته (مثل /etc/passwd در ویندوز) هم به‌درست
     * PathTraversal بدهد نه FileNotFound؛ مقایسه با separator انتهایی است
     * (base=/app دیگر /application را شامل نمی‌شود).
     */
    private function assertSafePath(string $path): void
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
        if ($base !== '' && !\Tavaan\Support\PathGuard::isInside($path, $base)) {
            throw new \Tavaan\Core\Exceptions\Filesystem\PathTraversalException('filesystem.path_traversal', ['path' => \basename($path)]);
        }

        $real = \realpath($path);
        if ($real === false || !\is_file($real)) {
            throw new \Tavaan\Core\Exceptions\Filesystem\FileNotFoundException('filesystem.file_not_found', ['path' => \basename($path)]);
        }
    }

    private function safeFilename(string $name): string
    {
        $name = (string) \preg_replace('/[\r\n"\']/', '', $name);

        return $name === '' ? 'download' : $name;
    }
}
