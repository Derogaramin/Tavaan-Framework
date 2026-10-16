<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Filesystem;

/**
 * تَوان | Tavaan — Contracts
 * FilesystemInterface — قرارداد درایور سامانهٔ فایل (DOC-063 §3/§11).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
interface FilesystemInterface
{
    /** نام disk. */
    public function diskName(): string;

    /** خواندن محتوا — null اگر وجود ندارد. */
    public function get(string $path): ?string;

    /** @return resource|null */
    public function readStream(string $path);

    /** نوشتن محتوا (🔒 اتمیک در درایور local). */
    public function put(string $path, string $content): bool;

    /** @param resource $stream */
    public function writeStream(string $path, $stream): bool;

    public function exists(string $path): bool;

    public function missing(string $path): bool;

    /** @param string|list<string> $paths */
    public function delete(string|array $paths): bool;

    public function copy(string $from, string $to): bool;

    public function move(string $from, string $to): bool;

    public function size(string $path): int;

    /** unix timestamp */
    public function lastModified(string $path): int;

    public function mimeType(string $path): string;

    public function append(string $path, string $content): bool;

    public function prepend(string $path, string $content): bool;

    /** @return list<string> فایل‌های مستقیم یک پوشه */
    public function files(string $directory = ''): array;

    /** @return list<string> فایل‌ها به‌صورت بازگشتی */
    public function allFiles(string $directory = ''): array;

    /** @return list<string> پوشه‌های مستقیم */
    public function directories(string $directory = ''): array;

    /** @return list<string> پوشه‌ها به‌صورت بازگشتی */
    public function allDirectories(string $directory = ''): array;

    public function makeDirectory(string $directory): bool;

    public function deleteDirectory(string $directory): bool;

    /** @return string 'public'|'private' */
    public function visibility(string $path): string;

    public function setVisibility(string $path, string $visibility): bool;

    /** URL عمومی (برای diskهای دارای `url`). */
    public function url(string $path): string;

    /** 🔒 URL امضاشدهٔ زمان‌دار (local: HMAC / s3: presigned). */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string;
}
