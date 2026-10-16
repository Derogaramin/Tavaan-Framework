<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

/**
 * تَوان | Tavaan — Backup
 * ZipReader — خوانندهٔ ZIP خالص (بدون ext-zip؛ مکمل ZipWriterPure موتور compat).
 *
 * ساختار: EOCD → Central Directory → Local Headers → داده (Store/Deflate).
 * 🔒 CRC-32 هر ورودی هنگام استخراج بررسی می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class ZipReader
{
    /** @var array<string, array{method: int, csize: int, size: int, crc: int, offset: int}> */
    private array $entries = [];

    /** @var resource|null */
    private $handle = null;

    private function __construct()
    {
    }

    public static function openFile(string $path): self
    {
        $handle = \fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open zip: ' . $path);
        }

        $reader = new self();
        $reader->handle = $handle;
        $reader->parseDirectory((int) \filesize($path));

        return $reader;
    }

    public static function openString(string $data): self
    {
        $path = \tempnam(\sys_get_temp_dir(), 'tvnzip');
        if ($path === false) {
            throw new \RuntimeException('tempnam failed');
        }
        \file_put_contents($path, $data);

        $reader = self::openFile($path);
        // فایل موقت تا close باز می‌ماند؛ پس از parse می‌توان حذفش کرد
        @\unlink($path);

        return $reader;
    }

    /** @return list<string> */
    public function entryNames(): array
    {
        $names = \array_keys($this->entries);
        \sort($names);

        return $names;
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    /** محتوای یک ورودی (یا null اگر موجود نیست). */
    public function extract(string $name): ?string
    {
        $entry = $this->entries[$name] ?? null;
        if ($entry === null || $this->handle === null) {
            return null;
        }

        $data = $this->readEntryData($entry);

        // 🔒 بررسی یکپارچگی CRC-32
        if (\crc32($data) !== $entry['crc']) {
            throw new \RuntimeException('Zip CRC mismatch for entry: ' . $name);
        }

        return $data;
    }

    public function close(): void
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
        $this->handle = null;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function parseDirectory(int $fileSize): void
    {
        if ($this->handle === null) {
            return;
        }

        // یافتن EOCD (حداکثر ۶۴KB+22 از انتها — سقف comment)
        $searchLen = (int) \min($fileSize, 66_000);
        \fseek($this->handle, $fileSize - $searchLen);
        $tail = (string) \fread($this->handle, $searchLen);

        $eocdPos = \strrpos($tail, "PK\x05\x06");
        if ($eocdPos === false) {
            throw new \RuntimeException('Not a valid zip (EOCD missing)');
        }

        $eocd = \substr($tail, $eocdPos, 22);
        if (\strlen($eocd) < 22) {
            throw new \RuntimeException('Truncated EOCD');
        }

        $unpacked = \unpack('vdisk/vcdDisk/vcountDisk/vcount/VcdSize/VcdOffset/vcommentLen', \substr($eocd, 4));
        if ($unpacked === false) {
            throw new \RuntimeException('Cannot parse EOCD');
        }

        $count = (int) $unpacked['count'];
        \fseek($this->handle, (int) $unpacked['cdOffset']);

        for ($i = 0; $i < $count; $i++) {
            $header = (string) \fread($this->handle, 46);
            if (\strlen($header) < 46 || !\str_starts_with($header, "PK\x01\x02")) {
                break;
            }

            $fields = \unpack('vverMade/vverNeed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/Voffset', \substr($header, 4));
            if ($fields === false) {
                break;
            }

            $name = (string) \fread($this->handle, (int) $fields['nameLen']);
            \fseek($this->handle, (int) $fields['extraLen'] + (int) $fields['commentLen'], \SEEK_CUR);

            $this->entries[$name] = [
                'method' => (int) $fields['method'],
                'csize' => (int) $fields['csize'],
                'size' => (int) $fields['size'],
                'crc' => (int) $fields['crc'],
                'offset' => (int) $fields['offset'],
            ];
        }
    }

    /** @param array{method: int, csize: int, size: int, crc: int, offset: int} $entry */
    private function readEntryData(array $entry): string
    {
        if ($this->handle === null) {
            return '';
        }

        \fseek($this->handle, $entry['offset']);
        $local = (string) \fread($this->handle, 30);
        if (\strlen($local) < 30 || !\str_starts_with($local, "PK\x03\x04")) {
            throw new \RuntimeException('Invalid local file header');
        }

        $localFields = \unpack('vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnameLen/vextraLen', \substr($local, 4));
        if ($localFields === false) {
            throw new \RuntimeException('Cannot parse local header');
        }

        \fseek($this->handle, (int) $localFields['nameLen'] + (int) $localFields['extraLen'], \SEEK_CUR);

        $data = '';
        $remaining = $entry['csize'];
        while ($remaining > 0) {
            $chunk = \fread($this->handle, (int) \min($remaining, 1_048_576));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= \strlen($chunk);
        }

        if ($entry['method'] === 8) {
            $inflated = @\gzinflate($data);
            if ($inflated === false) {
                throw new \RuntimeException('Deflate inflate failed');
            }

            return $inflated;
        }

        return $data;   // method 0 = Store
    }
}
