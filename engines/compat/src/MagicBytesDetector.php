<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

/**
 * تَوان | Tavaan — Compat
 * MagicBytesDetector — تشخیص MIME از امضای بایتی (بدون ext-fileinfo).
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class MagicBytesDetector
{
    /** @var array<string, list<array{0: string, 1: int}>> mime → [needle, offset] */
    private const SIGNATURES = [
        'image/jpeg'        => [["\xFF\xD8\xFF", 0]],
        'image/png'         => [["\x89PNG\r\n\x1A\n", 0]],
        'image/gif'         => [['GIF87a', 0], ['GIF89a', 0]],
        'image/webp'        => [['RIFF', 0]],
        'image/bmp'         => [['BM', 0]],
        'application/pdf'   => [['%PDF-', 0]],
        'application/zip'   => [["PK\x03\x04", 0], ["PK\x05\x06", 0]],
        'application/gzip'  => [["\x1F\x8B", 0]],
        'application/x-rar' => [["Rar!\x1A\x07\x00", 0], ["Rar!\x1A\x07\x01\x00", 0]],
        'application/x-7z-compressed' => [["7z\xBC\xAF\x27\x1C", 0]],
        'video/mp4'         => [['ftypisom', 4], ['ftypmp42', 4], ['ftypavc1', 4]],
        'video/webm'        => [["\x1A\x45\xDF\xA3", 0]],
        'audio/mpeg'        => [['ID3', 0], ["\xFF\xFB", 0]],
        'audio/ogg'         => [['OggS', 0]],
        'image/x-icon'      => [["\x00\x00\x01\x00", 0]],
        'font/woff'         => [['wOFF', 0]],
        'font/woff2'        => [['wOF2', 0]],
        'application/wasm'  => [["\x00asm", 0]],
    ];

    private function __construct()
    {
    }

    /**
     * تشخیص MIME از ۶۴+ بایت اول.
     *
     * @param string $head بایت‌های ابتدایی فایل
     */
    public static function detect(string $head, ?string $hintName = null): ?string
    {
        // WEBP نیازمند بررسی دوم است
        if (\substr($head, 0, 4) === 'RIFF' && \substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        foreach (self::SIGNATURES as $mime => $signatures) {
            foreach ($signatures as [$needle, $offset]) {
                if (\substr($head, $offset, \strlen($needle)) === $needle) {
                    return $mime;
                }
            }
        }

        // تشخیص متن
        if ($head !== '' && \preg_match('//u', $head) === 1
            && \preg_match('/[^\x09\x0A\x0D\x20-\x7E\xC2-\xF4\x80-\xBF]/', $head) === 0) {
            return 'text/plain';
        }

        return null;
    }

    /** تشخیص از فایل (۱۲۸ بایت اول). */
    public static function detectFromFile(string $path): ?string
    {
        $handle = @\fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $head = (string) \fread($handle, 128);
        \fclose($handle);

        return self::detect($head, \basename($path));
    }
}
