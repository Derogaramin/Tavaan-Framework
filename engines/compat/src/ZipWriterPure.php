<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

/**
 * تَوان | Tavaan — Compat
 * ZipWriterPure — تولید فایل ZIP با PHP خالص (Store + Deflate) بدون ext-zip.
 *
 * 🔒 AC-CMP-05: خروجی با `unzip -t` و ext-zip قابل باز شدن است.
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class ZipWriterPure
{
    /** @var list<array{name: string, crc: int, size: int, csize: int, method: int, offset: int}> */
    private array $entries = [];

    private string $buffer = '';

    /** افزودن ورودی از رشته (Deflate اگر zlib موجود و سودمند باشد). */
    public function addFromString(string $name, string $data, int $level = 6): void
    {
        $compressed = \function_exists('gzdeflate') && $level > 0 ? \gzdeflate($data, $level) : $data;
        $useDeflate = \is_string($compressed) && $compressed !== $data && \strlen($compressed) < \strlen($data);
        $method = $useDeflate ? 8 : 0;   // 8=Deflate, 0=Store
        $final = $useDeflate && \is_string($compressed) ? $compressed : $data;

        $offset = \strlen($this->buffer);
        $crc = \crc32($data);
        $size = \strlen($data);
        $csize = \strlen($final);

        // Local File Header
        $this->buffer .= "PK\x03\x04"
            . \pack('v', 20) . \pack('v', 0) . \pack('v', $method)
            . \pack('v', 0) . \pack('v', 0)
            . \pack('V', $crc) . \pack('V', $csize) . \pack('V', $size)
            . \pack('v', \strlen($name)) . \pack('v', 0)
            . $name . $final;

        $this->entries[] = [
            'name' => $name, 'crc' => $crc, 'size' => $size,
            'csize' => $csize, 'method' => $method, 'offset' => $offset,
        ];
    }

    /** افزودن فایل از دیسک. */
    public function addFile(string $path, ?string $name = null): void
    {
        $data = \file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException(\sprintf('Unable to read file for zip: %s', $path));
        }

        $this->addFromString($name ?? \basename($path), $data);
    }

    /** افزودن بازگشتی یک دایرکتوری. */
    public function addDirectory(string $dir, string $prefix = ''): void
    {
        $dir = \rtrim($dir, '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = \substr($file->getPathname(), \strlen($dir) + 1);
            $this->addFile($file->getPathname(), $prefix . \str_replace('\\', '/', $relative));
        }
    }

    /** خروجی نهایی (Central Directory + EOCD). */
    public function output(): string
    {
        $cd = '';
        foreach ($this->entries as $e) {
            $cd .= "PK\x01\x02" . \pack('v', 20) . \pack('v', 20) . \pack('v', 0)
                 . \pack('v', $e['method']) . \pack('v', 0) . \pack('v', 0)
                 . \pack('V', $e['crc']) . \pack('V', $e['csize']) . \pack('V', $e['size'])
                 . \pack('v', \strlen($e['name'])) . \pack('v', 0) . \pack('v', 0)
                 . \pack('v', 0) . \pack('v', 0) . \pack('V', 0)
                 . \pack('V', $e['offset']) . $e['name'];
        }

        $cdOffset = \strlen($this->buffer);
        $cdSize = \strlen($cd);
        $count = \count($this->entries);

        return $this->buffer . $cd . "PK\x05\x06"
             . \pack('v', 0) . \pack('v', 0)
             . \pack('v', $count) . \pack('v', $count)
             . \pack('V', $cdSize) . \pack('V', $cdOffset) . \pack('v', 0);
    }

    /** نوشتن در فایل. */
    public function saveTo(string $path): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }
        \file_put_contents($path, $this->output());
    }

    /** @return list<string> نام ورودی‌ها */
    public function entryNames(): array
    {
        return \array_map(static fn (array $e): string => $e['name'], $this->entries);
    }
}
