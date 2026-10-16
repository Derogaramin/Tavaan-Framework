<?php

declare(strict_types=1);

namespace Tavaan\Engine\Mail;

/**
 * تَوان | Tavaan — Mail
 * Attachment — پیوست ایمیل (فایل یا دادهٔ خام؛ DOC-065 §4).
 *
 * 🔒 محتوا به‌شکل base64 نگه داشته می‌شود — JSON-safe برای
 * سریال‌سازی صف (Mail::queue) و آماده برای MIME transfer-encoding.
 *
 * @api 1.0
 * @see docs/03-engines/mail.md
 */
final class Attachment
{
    /** @param string $contentBase64 محتوای base64 (بدون line-break) */
    public function __construct(
        public readonly string $contentBase64,
        public readonly string $filename,
        public readonly string $mime,
        public readonly bool $inline,
        public readonly ?string $cid,
    ) {
    }

    /** پیوست از مسیر فایل. */
    public static function fromPath(string $path, ?string $as = null, ?string $mime = null): self
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw MailException::attachmentUnreadable($path);
        }

        $content = \file_get_contents($path);
        if ($content === false) {
            throw MailException::attachmentUnreadable($path);
        }

        return new self(
            \base64_encode($content),
            $as ?? \basename($path),
            $mime ?? self::guessMime($path),
            false,
            null,
        );
    }

    /** پیوست از دادهٔ خام. */
    public static function fromData(string $data, string $name, ?string $mime = null): self
    {
        return new self(\base64_encode($data), $name, $mime ?? 'application/octet-stream', false, null);
    }

    /** تصویر inline با Content-ID (برای ارجاع `cid:` در HTML). */
    public static function inline(string $data, string $name, string $cid, ?string $mime = null): self
    {
        return new self(\base64_encode($data), $name, $mime ?? self::guessMime($name), true, $cid);
    }

    public function content(): string
    {
        return (string) \base64_decode($this->contentBase64, true);
    }

    public function size(): int
    {
        return \strlen($this->content());
    }

    /** 🔒 filename امن (بدون path/کنترل). */
    public function safeFilename(): string
    {
        $name = \basename(\str_replace('\\', '/', $this->filename));
        $name = (string) \preg_replace('/[\x00-\x1F\x7F"\/]/', '_', $name);

        return $name === '' ? 'attachment' : $name;
    }

    private static function guessMime(string $pathOrName): string
    {
        $ext = \strtolower(\pathinfo($pathOrName, \PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'html' => 'text/html',
            default => 'application/octet-stream',
        };
    }
}
